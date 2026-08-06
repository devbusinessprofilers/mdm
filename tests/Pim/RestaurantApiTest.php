<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Entity\Restaurant\Restaurant;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('database')]
final class RestaurantApiTest extends WebTestCase
{
    private const TABLES = ['pim_restaurant_acces', 'pim_restaurant_periode_fermeture', 'pim_restaurant_salle', 'pim_restaurant', 'pim_fiche_search', 'pim_fiche_attribute_value', 'pim_fiche', 'pim_localisation', 'outbox_message'];

    private Connection $connection;
    private EntityManagerInterface $entityManager;
    /** @var \OpenSSLAsymmetricKey */
    private $privateKey;
    private string $publicKeyFile;

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
            foreach (self::TABLES as $table) {
                $this->connection->executeStatement('DELETE FROM '.$table);
            }
        }
        if (isset($this->publicKeyFile)) { @unlink($this->publicKeyFile); }
        putenv('EXTERNAL_SITE_JWT_PUBLIC_KEY');
        unset($_ENV['EXTERNAL_SITE_JWT_PUBLIC_KEY'], $_SERVER['EXTERNAL_SITE_JWT_PUBLIC_KEY']);

        parent::tearDown();
    }

    public function testRestaurantsAreServedByTheExternalApiFirewall(): void
    {
        $client = $this->client();
        $restaurant = new Restaurant();
        $restaurant->changeLabel('Restaurant API');
        $restaurant->fiche()->publishForImport();
        $this->entityManager->persist($restaurant);
        $this->entityManager->flush();

        $client->request('GET', '/api/v1/restaurants/'.$restaurant->id(), server: $this->headers());
        self::assertResponseIsSuccessful();
        $read = $this->json($client);
        self::assertSame($restaurant->id(), $read['id']);
        self::assertSame('Restaurant API', $read['label']);

        $client->request('GET', '/api/v1/restaurants', server: $this->headers());
        self::assertResponseIsSuccessful();
        self::assertSame($restaurant->id(), $this->json($client)[0]['id']);

        $client->request('GET', '/api/v1/restaurants/'.$restaurant->id());
        self::assertResponseStatusCodeSame(401);
    }

    private function client(): KernelBrowser
    {
        $client = self::createClient();
        $this->connection = self::getContainer()->get(Connection::class);
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        foreach (self::TABLES as $table) {
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
}
