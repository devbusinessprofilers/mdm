<?php

declare(strict_types=1);

namespace App\Tests\Dam;

use App\Dam\Entity\MediaAsset;
use App\Dam\Entity\MediaRendition;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Enum\NatureRessource;
use App\Pim\Message\IndexFiche;
use App\Shared\Service\PrivateObjectStorageInterface;
use App\Shared\Service\PublicObjectStorageInterface;
use App\Tests\Support\StockageMemoire;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Uid\Ulid;

/**
 * Une photo de Restaurant déposée depuis l'éditeur est rangée sous `lieux/` ;
 * la commande la recopie sous `restaurants/` (original et rendus), met à jour
 * les clés, replanifie la synchronisation marketplace et ne purge les anciens
 * objets que sur demande.
 */
#[Group('database')]
final class ReclasserOriginauxCommandTest extends KernelTestCase
{
    private const TABLES = ['outbox_message', 'pim_ressource_lieu', 'dam_media_rendition', 'dam_media_asset', 'pim_restaurant', 'pim_fiche_search', 'pim_fiche', 'pim_localisation'];

    private Connection $connection;
    private EntityManagerInterface $entityManager;
    private StockageMemoire $prive;
    private StockageMemoire $public;
    private string $base;

    protected function setUp(): void
    {
        if (!str_starts_with((string) getenv('TEST_MESSENGER_PIM_DSN'), 'doctrine://')) {
            self::markTestSkipped('Set TEST_MESSENGER_PIM_DSN to a Doctrine transport to run database integration tests.');
        }
        self::bootKernel();
        $this->connection = self::getContainer()->get(Connection::class);
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->prive = new StockageMemoire();
        $this->public = new StockageMemoire();
        self::getContainer()->set(PrivateObjectStorageInterface::class, $this->prive);
        self::getContainer()->set(PublicObjectStorageInterface::class, $this->public);
        $prefix = trim((string) ($_SERVER['S3_PREFIX'] ?? $_ENV['S3_PREFIX'] ?? ''), '/');
        $this->base = '' === $prefix ? '' : $prefix.'/';
        $this->clear();
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->clear();
        }
        parent::tearDown();
    }

    public function testLeRapportNeModifieRienEtLApplicationReclasseOriginalEtRendus(): void
    {
        [$ficheId, $mediaId] = $this->photoDeRestaurantRangeeSousLieux();
        $ancienOriginal = $this->base.'lieux/'.$ficheId.'/'.$mediaId.'/original.jpg';
        $ancienRendu = $this->base.'photos/large/lieux/'.$ficheId.'/'.$mediaId.'-abcd1234.webp';

        $rapport = $this->tester();
        $rapport->execute([]);
        self::assertStringContainsString('lieux → restaurants', $rapport->getDisplay());
        self::assertStringContainsString('1 photo(s) à reclasser', $rapport->getDisplay());
        self::assertSame([$ancienOriginal], $this->prive->cles());
        self::assertSame(0, $this->outboxCount());

        $application = $this->tester();
        $application->execute(['--appliquer' => true]);
        self::assertStringContainsString('1 photo(s) reclassée(s)', $application->getDisplay());
        $nouvelOriginal = $this->base.'restaurants/'.$ficheId.'/'.$mediaId.'/original.jpg';
        $nouveauRendu = $this->base.'photos/large/restaurants/'.$ficheId.'/'.$mediaId.'-abcd1234.webp';
        self::assertSame([$ancienOriginal, $nouvelOriginal], $this->prive->cles(), 'Copié, ancien conservé tant que --purger n’est pas passé.');
        self::assertSame('jpeg-bytes', $this->prive->read($nouvelOriginal));
        self::assertSame('webp-bytes', $this->public->read($nouveauRendu));
        self::assertSame($nouvelOriginal, $this->connection->fetchOne('SELECT original_storage_key FROM dam_media_asset'));
        self::assertSame($nouveauRendu, $this->connection->fetchOne('SELECT storage_key FROM dam_media_rendition'));
        self::assertSame(1, $this->outboxCount(), 'La fiche est replanifiée vers la marketplace (clés des rendus dans le payload).');

        $relance = $this->tester();
        $relance->execute(['--appliquer' => true, '--purger' => true]);
        self::assertStringContainsString('0 photo(s) reclassée(s)', $relance->getDisplay(), 'Idempotent : plus rien à reclasser.');
        self::assertTrue($this->prive->exists($ancienOriginal), 'Rien à purger quand rien n’a été reclassé dans la passe.');
    }

    public function testLaPurgeSupprimeLesAnciensObjetsApresCopie(): void
    {
        [$ficheId, $mediaId] = $this->photoDeRestaurantRangeeSousLieux();

        $this->tester()->execute(['--appliquer' => true, '--purger' => true]);

        self::assertSame([$this->base.'restaurants/'.$ficheId.'/'.$mediaId.'/original.jpg'], $this->prive->cles());
        self::assertSame([$this->base.'photos/large/restaurants/'.$ficheId.'/'.$mediaId.'-abcd1234.webp'], $this->public->cles());
    }

    /** @return array{0: string, 1: string} identifiants de la fiche et du média */
    private function photoDeRestaurantRangeeSousLieux(): array
    {
        $restaurant = new Restaurant();
        $restaurant->changeLabel('Brasserie mal rangée');
        $ficheId = $restaurant->fiche()->idString();
        $mediaId = new Ulid();
        $original = $this->base.'lieux/'.$ficheId.'/'.$mediaId.'/original.jpg';
        $rendu = $this->base.'photos/large/lieux/'.$ficheId.'/'.$mediaId.'-abcd1234.webp';
        $asset = new MediaAsset($mediaId, $original, 'photo.jpg', 'image/jpeg', 10, str_repeat('a', 64));
        $rendition = new MediaRendition($asset, 'large', $rendu, 960, 480, 5);
        $asset->renditions()->add($rendition);
        $this->prive->write($original, 'jpeg-bytes');
        $this->public->write($rendu, 'webp-bytes');

        $resource = new RessourceLieu();
        $resource->changeDamAssetId((string) $mediaId);
        $resource->changeNature(NatureRessource::Photo);
        $resource->changeUsage('PHOTO_DIVERSE');
        $restaurant->fiche()->addResource($resource);

        $this->entityManager->persist($asset);
        $this->entityManager->persist($rendition);
        $this->entityManager->persist($restaurant);
        $this->entityManager->flush();
        $this->entityManager->clear();
        $this->connection->executeStatement('DELETE FROM outbox_message');

        return [$ficheId, (string) $mediaId];
    }

    private function outboxCount(): int
    {
        return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM outbox_message WHERE message_type = ?', [IndexFiche::class]);
    }

    private function tester(): CommandTester
    {
        $application = new Application(self::$kernel ?? throw new \LogicException('Kernel non démarré.'));

        return new CommandTester($application->find('app:dam:reclasser-originaux'));
    }

    private function clear(): void
    {
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        foreach (self::TABLES as $table) {
            $this->connection->executeStatement('DELETE FROM '.$table);
        }
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
    }
}
