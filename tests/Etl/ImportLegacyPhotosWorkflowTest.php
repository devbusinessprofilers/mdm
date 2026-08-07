<?php

declare(strict_types=1);

namespace App\Tests\Etl;

use App\Etl\Entity\LegacyFicheMapping;
use App\Pim\Entity\Lieu\Lieu;
use App\Shared\Service\PrivateObjectStorageInterface;
use App\Shared\Service\PublicObjectStorageInterface;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * L'import des photos ne doit pas dépublier les fiches : l'attache d'une
 * ressource passe par markChanged, neutralisé via preserveWorkflowDuring.
 * Couvre les deux sources d'originaux : répertoire local (--images-dir) et
 * dossier tmp/ du bucket S3 privé (mode par défaut).
 */
#[Group('database')]
final class ImportLegacyPhotosWorkflowTest extends KernelTestCase
{
    private const TABLES = ['etl_legacy_photo', 'etl_legacy_fiche', 'pim_ressource_lieu', 'dam_media_rendition', 'dam_media_asset', 'pim_fiche_search', 'pim_fiche_attribute_value', 'pim_lieu_administratif', 'pim_lieu_tarification', 'pim_lieu', 'pim_fiche', 'pim_localisation', 'outbox_message'];

    private Connection $connection;
    private string $imagesDir;

    protected function setUp(): void
    {
        if (!str_starts_with((string) getenv('TEST_MESSENGER_PIM_DSN'), 'doctrine://')) {
            self::markTestSkipped('Set TEST_MESSENGER_PIM_DSN to a Doctrine transport to run database integration tests.');
        }
        self::bootKernel();
        $this->connection = self::getContainer()->get(Connection::class);
        $this->cleanTables();
        $this->imagesDir = sys_get_temp_dir().'/mdm-legacy-images-'.bin2hex(random_bytes(4));
        mkdir($this->imagesDir.'/x/master', 0777, true);
        file_put_contents($this->imagesDir.'/x/master/1.jpg', $this->png(960, 480));
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->cleanTables();
        }
        if (isset($this->imagesDir)) {
            @unlink($this->imagesDir.'/x/master/1.jpg');
            @rmdir($this->imagesDir.'/x/master');
            @rmdir($this->imagesDir.'/x');
            @rmdir($this->imagesDir);
        }

        parent::tearDown();
    }

    public function testPhotoImportKeepsPublishedStatus(): void
    {
        $this->givenStorages(new PhotosWorkflowTestStorage());
        $this->givenPublishedLieuWithPhoto();

        $tester = $this->tester();
        $tester->execute(['--images-dir' => $this->imagesDir, '--syspad' => '9100']);

        $status = $this->connection->fetchOne('SELECT status FROM pim_fiche WHERE code = 9100');
        self::assertSame('publiee', $status, $tester->getDisplay());
    }

    public function testS3SourceImportsUnderEnvPrefix(): void
    {
        $storage = new PhotosWorkflowTestStorage(['tmp/x/master/1.jpg' => $this->png(960, 480)]);
        $this->givenStorages($storage);
        $this->givenPublishedLieuWithPhoto();

        $tester = $this->tester();
        $tester->execute(['--syspad' => '9100']);

        self::assertSame('done', $this->connection->fetchOne('SELECT status FROM etl_legacy_photo LIMIT 1'), $tester->getDisplay());
        self::assertSame('publiee', $this->connection->fetchOne('SELECT status FROM pim_fiche WHERE code = 9100'));
        // L'original est déposé sous le préfixe d'environnement, jamais sous tmp/.
        $originals = array_filter($storage->writtenKeys(), static fn (string $key): bool => str_contains($key, '/lieux/'));
        self::assertNotEmpty($originals, implode("\n", $storage->writtenKeys()));
        foreach ($originals as $key) {
            self::assertStringStartsNotWith('tmp/', $key);
        }
    }

    public function testS3SourceMarksMissingObject(): void
    {
        $this->givenStorages(new PhotosWorkflowTestStorage());
        $this->givenPublishedLieuWithPhoto();

        $tester = $this->tester();
        $tester->execute(['--syspad' => '9100']);

        self::assertSame('missing_file', $this->connection->fetchOne('SELECT status FROM etl_legacy_photo LIMIT 1'), $tester->getDisplay());
    }

    private function givenStorages(PhotosWorkflowTestStorage $privateStorage): void
    {
        self::getContainer()->set(PrivateObjectStorageInterface::class, $privateStorage);
        self::getContainer()->set(PublicObjectStorageInterface::class, new PhotosWorkflowTestStorage());
    }

    private function givenPublishedLieuWithPhoto(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $lieu = new Lieu();
        $lieu->changeLabel('Lieu publié avec photo');
        $lieu->fiche()->assignImportedCode(9100);
        $lieu->fiche()->publishForImport();
        $entityManager->persist($lieu);
        $entityManager->persist(new LegacyFicheMapping(9100, $lieu->fiche()->id(), 'Lieu publié avec photo', 'Hôtel', '{"master":["x/master/1.jpg"]}'));
        $entityManager->flush();
        $entityManager->clear();
    }

    private function tester(): CommandTester
    {
        $application = new Application(self::$kernel);

        return new CommandTester($application->find('app:legacy:import-photos'));
    }

    private function png(int $width, int $height): string
    {
        $chunk = static fn (string $type, string $data): string => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
        $compressed = gzcompress(str_repeat("\0".str_repeat("\0", $width * 3), $height), 9);
        self::assertIsString($compressed);

        return "\x89PNG\r\n\x1a\n"
            .$chunk('IHDR', pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0))
            .$chunk('IDAT', $compressed)
            .$chunk('IEND', '');
    }

    private function cleanTables(): void
    {
        foreach (self::TABLES as $table) {
            $this->connection->executeStatement('DELETE FROM '.$table);
        }
    }
}

/**
 * Stockage privé en mémoire : chaque readStream recrée un flux (l'import lit
 * l'objet tmp/ puis MediaProcessingService relit l'original déposé).
 */
final class PhotosWorkflowTestStorage implements PrivateObjectStorageInterface, PublicObjectStorageInterface
{
    /** @var array<string, string> */
    private array $objects;
    /** @var list<string> */
    private array $written = [];

    /** @param array<string, string> $seed */
    public function __construct(array $seed = [])
    {
        $this->objects = $seed;
    }

    /** @return list<string> */
    public function writtenKeys(): array
    {
        return $this->written;
    }

    public function write(string $key, string $contents, array $options = []): void
    {
        $this->objects[$key] = $contents;
        $this->written[] = $key;
    }

    public function writeStream(string $key, mixed $stream, array $options = []): void
    {
        $this->objects[$key] = (string) stream_get_contents($stream);
        $this->written[] = $key;
    }

    public function read(string $key): string
    {
        return $this->objects[$key] ?? throw new \RuntimeException(sprintf('Objet inconnu : %s', $key));
    }

    public function readStream(string $key): mixed
    {
        $stream = fopen('php://temp', 'r+b');
        if (false === $stream) {
            throw new \RuntimeException('Flux temporaire indisponible.');
        }
        fwrite($stream, $this->read($key));
        rewind($stream);

        return $stream;
    }

    public function exists(string $key): bool
    {
        return \array_key_exists($key, $this->objects);
    }

    public function temporaryUrl(string $key, \DateTimeInterface $expiresAt): string
    {
        return 'https://private.example.test/'.$key;
    }

    public function delete(string $key): void
    {
        unset($this->objects[$key]);
    }

    public function deleteDirectory(string $prefix): void
    {
        foreach (array_keys($this->objects) as $key) {
            if (str_starts_with($key, rtrim($prefix, '/').'/')) {
                unset($this->objects[$key]);
            }
        }
    }
}
