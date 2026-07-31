<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Enum\NatureRessource;
use App\Shared\Service\PrivateObjectStorageInterface;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

#[Group('database')]
final class LieuApiTest extends WebTestCase
{
    private Connection $connection;
    private EntityManagerInterface $entityManager;
    /** @var \OpenSSLAsymmetricKey */
    private $privateKey;
    private string $publicKeyFile;
    private ?string $imageFile = null;

    protected function setUp(): void
    {
        if (!str_starts_with((string) getenv('TEST_MESSENGER_PIM_DSN'), 'doctrine://')) {
            self::markTestSkipped('Set TEST_MESSENGER_PIM_DSN to a Doctrine transport to run database integration tests.');
        }
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        self::assertInstanceOf(\OpenSSLAsymmetricKey::class, $key);
        $details = openssl_pkey_get_details($key);
        self::assertIsArray($details);
        $path = tempnam(sys_get_temp_dir(), 'mdm-api-public-');
        self::assertIsString($path);
        file_put_contents($path, $details['key']);
        $this->privateKey = $key;
        $this->publicKeyFile = $path;
        putenv('EXTERNAL_SITE_JWT_PUBLIC_KEY='.$path);
        $_ENV['EXTERNAL_SITE_JWT_PUBLIC_KEY'] = $path;
        $_SERVER['EXTERNAL_SITE_JWT_PUBLIC_KEY'] = $path;

        parent::setUp();
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            foreach (['pim_ressource_lieu', 'dam_media_rendition', 'dam_media_asset', 'pim_fiche_search', 'pim_fiche_attribute_value', 'pim_salle', 'pim_periode_fermeture', 'pim_acces_lieu', 'pim_lieu_administratif', 'pim_lieu_tarification', 'pim_lieu', 'pim_fiche', 'pim_localisation', 'outbox_message'] as $table) {
                $this->connection->executeStatement('DELETE FROM '.$table);
            }
        }
        if (isset($this->publicKeyFile)) { @unlink($this->publicKeyFile); }
        if (null !== $this->imageFile) { @unlink($this->imageFile); }
        putenv('EXTERNAL_SITE_JWT_PUBLIC_KEY');
        unset($_ENV['EXTERNAL_SITE_JWT_PUBLIC_KEY'], $_SERVER['EXTERNAL_SITE_JWT_PUBLIC_KEY']);

        parent::tearDown();
    }

    public function testReadPatchConflictAndStatusPreservation(): void
    {
        $client = $this->client();
        $lieu = new Lieu();
        $lieu->changeCode(4201);
        $lieu->changeLabel('Lieu API initial');
        $lieu->fiche()->publishForImport();
        $this->entityManager->persist($lieu);
        $this->entityManager->flush();
        $version = $lieu->fiche()->version();

        $client->request('GET', '/api/v1/lieux/'.$lieu->id(), server: $this->headers());
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('etag', '"'.$version.'"');
        $read = $this->json($client);
        self::assertSame($lieu->id(), $read['id']);
        self::assertSame('Lieu API initial', $read['label']);
        self::assertSame('publiee', $read['status']);
        self::assertSame($version, $read['version']);

        $client->request('PATCH', '/api/v1/lieux/'.$lieu->id(), server: $this->headers(['CONTENT_TYPE' => 'application/merge-patch+json']), content: '{"label":"Sans version"}');
        self::assertResponseStatusCodeSame(428);
        self::assertSame('precondition_required', $this->json($client)['type']);

        $client->request('PATCH', '/api/v1/lieux/'.$lieu->id(), server: $this->headers(['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_IF_MATCH' => '"999"']), content: '{"label":"Conflit"}');
        self::assertResponseStatusCodeSame(409);
        $conflict = $this->json($client);
        self::assertSame('version_conflict', $conflict['type']);
        self::assertSame($version, $conflict['currentVersion']);

        $client->request('PATCH', '/api/v1/lieux/'.$lieu->id(), server: $this->headers(['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_IF_MATCH' => '"'.$version.'"']), content: json_encode([
            'label' => 'Lieu API modifié',
            'generaleWebsiteUrl' => 'https://example.test',
            'localisation' => ['pays' => 'France', 'ville' => 'Paris'],
        ], JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();
        $patched = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('Lieu API modifié', $patched['label']);
        self::assertSame('publiee', $patched['status']);
        self::assertSame('Paris', $patched['localisation']['ville']);
        self::assertGreaterThan($version, $patched['version']);
    }

    public function testListFiltersErrorsAndAuthenticationAreStable(): void
    {
        $client = $this->client();
        $lieu = new Lieu();
        $lieu->changeLabel('Lieu listé');
        $this->entityManager->persist($lieu);
        $second = new Lieu();
        $second->changeLabel('Second lieu');
        $this->entityManager->persist($second);
        $this->entityManager->flush();

        $client->request('GET', '/api/v1/lieux?status=en_cours&limit=1', server: $this->headers());
        self::assertResponseIsSuccessful();
        $list = $this->json($client);
        self::assertContains($list[0]['id'], [$lieu->id(), $second->id()]);
        self::assertContains($list[0]['label'], ['Lieu listé', 'Second lieu']);
        self::assertSame('en_cours', $list[0]['status']);
        self::assertNotSame('', $client->getResponse()->headers->get('X-Next-Cursor', ''));

        $client->request('GET', '/api/v1/lieux?status=inconnu', server: $this->headers());
        self::assertResponseStatusCodeSame(400);
        self::assertSame('invalid_filter', $this->json($client)['type']);

        $client->request('GET', '/api/v1/lieux/'.$lieu->id());
        self::assertResponseStatusCodeSame(401);

        $client->request('POST', '/api/v1/lieux', server: $this->headers(['CONTENT_TYPE' => 'application/json']), content: '{}');
        self::assertResponseStatusCodeSame(405);

        $client->request('PATCH', '/api/v1/lieux/'.$lieu->id(), server: $this->headers(['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_IF_MATCH' => '"'.$lieu->fiche()->version().'"']), content: '{"status":"publiee"}');
        self::assertResponseStatusCodeSame(422);
        self::assertSame('invalid_fields', $this->json($client)['type']);
    }

    public function testMediaMetadataOrderAndDeleteUseTheLieuVersion(): void
    {
        $client = $this->client();
        $lieu = new Lieu();
        $lieu->changeLabel('Lieu médias API');
        $resource = new RessourceLieu();
        $resource->changeDamAssetId('01KYSQC10N1MK6RW1T4DY2TZX7');
        $resource->changeNature(NatureRessource::Photo);
        $resource->changeUsage('PHOTO_DIVERSE');
        $lieu->addRessource($resource);
        $lieu->fiche()->publishForImport();
        $this->entityManager->persist($lieu);
        $this->entityManager->flush();
        $version = $lieu->fiche()->version();

        $client->request('PATCH', '/api/v1/lieux/'.$lieu->id().'/medias/'.$resource->id(), server: $this->headers(['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_IF_MATCH' => '"'.$version.'"']), content: '{"legende":"Vue principale","rotation":90,"rightsGranted":true}');
        self::assertResponseIsSuccessful();
        $media = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('Vue principale', $media['legende']);
        self::assertSame(90, $media['rotation']);
        self::assertTrue($media['rightsGranted']);

        $version = (int) trim((string) $client->getResponse()->headers->get('etag'), '"');
        $client->request('PUT', '/api/v1/lieux/'.$lieu->id().'/medias/ordre', server: $this->headers(['CONTENT_TYPE' => 'application/json', 'HTTP_IF_MATCH' => '"'.$version.'"']), content: json_encode(['ids' => [$resource->id()]], JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();
        self::assertSame('publiee', $this->json($client)['status']);

        $version = (int) trim((string) $client->getResponse()->headers->get('etag'), '"');
        $client->request('DELETE', '/api/v1/lieux/'.$lieu->id().'/medias/'.$resource->id(), server: $this->headers(['HTTP_IF_MATCH' => '"'.$version.'"']));
        self::assertResponseStatusCodeSame(204);
    }

    public function testImageUploadReturnsDamVariantsAndQueuesProcessing(): void
    {
        $client = $this->client();
        $lieu = new Lieu();
        $lieu->changeLabel('Lieu upload API');
        $this->entityManager->persist($lieu);
        $this->entityManager->flush();
        $path = tempnam(sys_get_temp_dir(), 'mdm-api-image-');
        self::assertIsString($path);
        $this->imageFile = $path;
        file_put_contents($path, $this->png(960, 480));

        $client->request(
            'POST',
            '/api/v1/lieux/'.$lieu->id().'/medias',
            ['usage' => 'PHOTO_PRINCIPALE', 'legende' => 'Façade'],
            ['photo' => new UploadedFile($path, 'facade.png', 'image/png', null, true)],
            $this->headers(['HTTP_IF_MATCH' => '"'.$lieu->fiche()->version().'"']),
        );
        self::assertResponseStatusCodeSame(201);
        $media = $this->json($client);
        self::assertSame('PHOTO_PRINCIPALE', $media['usage']);
        self::assertSame('Façade', $media['legende']);
        self::assertCount(6, $media['variants']);
        self::assertSame(['large', 'medium_2', 'medium', 'small', 'map', 'cart'], array_column($media['variants'], 'name'));
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM dam_media_asset'));
        self::assertGreaterThanOrEqual(2, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM outbox_message'));
    }

    private function client(): KernelBrowser
    {
        $client = self::createClient();
        $this->connection = self::getContainer()->get(Connection::class);
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::getContainer()->set(PrivateObjectStorageInterface::class, new ApiTestObjectStorage());
        foreach (['pim_ressource_lieu', 'dam_media_rendition', 'dam_media_asset', 'pim_fiche_search', 'pim_fiche_attribute_value', 'pim_salle', 'pim_periode_fermeture', 'pim_acces_lieu', 'pim_lieu_administratif', 'pim_lieu_tarification', 'pim_lieu', 'pim_fiche', 'pim_localisation', 'outbox_message'] as $table) {
            $this->connection->executeStatement('DELETE FROM '.$table);
        }

        return $client;
    }

    /**
     * @param array<string, string> $extra
     * @return array<string, string>
     */
    private function headers(array $extra = []): array
    {
        return $extra + ['HTTP_AUTHORIZATION' => 'Bearer '.$this->jwt(), 'HTTP_ACCEPT' => 'application/json'];
    }

    private function jwt(): string
    {
        $encode = static fn (array $value): string => rtrim(strtr(base64_encode(json_encode($value, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
        $header = $encode(['alg' => 'RS256', 'typ' => 'JWT']);
        $now = time();
        $payload = $encode(['iss' => 'external-site', 'aud' => 'mdm', 'sub' => 'external-site', 'iat' => $now, 'exp' => $now + 300, 'jti' => bin2hex(random_bytes(8))]);
        self::assertTrue(openssl_sign($header.'.'.$payload, $signature, $this->privateKey, OPENSSL_ALGO_SHA256));

        return $header.'.'.$payload.'.'.rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
    }

    /** @return array<array-key, mixed> */
    private function json(KernelBrowser $client): array
    {
        $data = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }

    private function png(int $width, int $height): string
    {
        $chunk = static function (string $type, string $data): string {
            return pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
        };
        $compressed = gzcompress(str_repeat("\0".str_repeat("\0", $width * 3), $height), 9);
        self::assertIsString($compressed);

        return "\x89PNG\r\n\x1a\n"
            .$chunk('IHDR', pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0))
            .$chunk('IDAT', $compressed)
            .$chunk('IEND', '');
    }
}

final class ApiTestObjectStorage implements PrivateObjectStorageInterface
{
    public function write(string $key, string $contents, array $options = []): void {}
    public function writeStream(string $key, mixed $stream, array $options = []): void {}
    public function read(string $key): string { return ''; }
    public function readStream(string $key): mixed { $stream = fopen('php://temp', 'r+b'); if (false === $stream) { throw new \RuntimeException('Flux temporaire indisponible.'); } return $stream; }
    public function exists(string $key): bool { return false; }
    public function temporaryUrl(string $key, \DateTimeInterface $expiresAt): string { return 'https://private.example.test/'.$key; }
    public function delete(string $key): void {}
}
