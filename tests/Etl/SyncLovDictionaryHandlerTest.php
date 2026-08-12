<?php

declare(strict_types=1);

namespace App\Tests\Etl;

use App\Enrichment\Entity\AttributeValueTranslation;
use App\Enrichment\Enum\SupportedLocale;
use App\Etl\Message\SyncLovDictionary;
use App\Etl\MessageHandler\SyncLovDictionaryHandler;
use App\Etl\Service\MarketplaceClientInterface;
use App\Pim\Entity\AttributDefinition;
use App\Pim\Entity\ValeurAttribut;
use App\Pim\Lov\LovRuntimeCatalog;
use App\Pim\Service\LovAdminManager;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('database')]
final class SyncLovDictionaryHandlerTest extends KernelTestCase
{
    private const ATTRIBUTE_ID = 999999901;
    private const ATTRIBUTE_CODE = 'TEST_MARKETPLACE_LOV';
    private const VALUE_ID = 999999902;
    private const VALUE_CODE = 'TEST_MARKETPLACE_LOV_1';

    private Connection $connection;
    private EntityManagerInterface $entityManager;
    private RecordingMarketplaceClient $client;

    protected function setUp(): void
    {
        if (!str_starts_with((string) getenv('TEST_MESSENGER_PIM_DSN'), 'doctrine://')) {
            self::markTestSkipped('Database integration is disabled.');
        }
        self::bootKernel();
        $this->client = new RecordingMarketplaceClient();
        self::getContainer()->set(MarketplaceClientInterface::class, $this->client);
        $this->connection = self::getContainer()->get(Connection::class);
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->clear();
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->clear();
            // Le dictionnaire runtime statique a pu absorber l'attribut de
            // test : le recharger depuis la base nettoyée.
            self::getContainer()->get(LovRuntimeCatalog::class)->reload();
        }
        parent::tearDown();
    }

    public function testDictionarySnapshotIsPushed(): void
    {
        $value = $this->testValue();
        $translation = new AttributeValueTranslation($value, SupportedLocale::En, $value->label());
        $translation->applyManual('Test value', 'tester');
        $pending = new AttributeValueTranslation($value, SupportedLocale::De, $value->label());
        $this->entityManager->persist($translation);
        $this->entityManager->persist($pending);
        $this->entityManager->flush();

        $handler = self::getContainer()->get(SyncLovDictionaryHandler::class);
        $handler(new SyncLovDictionary());

        self::assertCount(1, $this->client->lovUpserts);
        $payload = $this->client->lovUpserts[0];
        self::assertNotEmpty($payload['sequence']);
        $attributes = array_column($payload['attributes'], null, 'code');
        // Le dictionnaire statique des catalogues est complet, chaque
        // attribut porte sa famille pour le rangement dans l'admin.
        self::assertArrayHasKey('GENERALE_TYPOLOGIE', $attributes);
        self::assertArrayHasKey('TYPE_PRESTATAIRE', $attributes);
        self::assertSame('lieu', $attributes['GENERALE_TYPOLOGIE']['famille']);
        self::assertSame('prestataire', $attributes['TYPE_PRESTATAIRE']['famille']);
        self::assertSame('activite', $attributes['THEMATIQUE_ACTIVITE']['famille']);
        self::assertSame('restaurant', $attributes['TYPE_RESTAURANT']['famille']);
        self::assertSame('autre', $attributes[self::ATTRIBUTE_CODE]['famille']);
        // Les lignes en base sont poussées, désactivées comprises, avec les
        // seules traductions disponibles.
        self::assertArrayHasKey(self::ATTRIBUTE_CODE, $attributes);
        $values = array_column($attributes[self::ATTRIBUTE_CODE]['values'], null, 'code');
        self::assertSame('Valeur de test', $values[self::VALUE_CODE]['label']);
        self::assertSame(5, $values[self::VALUE_CODE]['position']);
        self::assertFalse($values[self::VALUE_CODE]['active']);
        self::assertSame(['en' => 'Test value'], $values[self::VALUE_CODE]['translations']);
    }

    public function testAdminUpdateSchedulesDictionarySync(): void
    {
        $value = $this->testValue();
        $this->entityManager->flush();

        $manager = self::getContainer()->get(LovAdminManager::class);
        $manager->update($value, ['label' => 'Valeur renommée', 'position' => 5, 'active' => true], 'tester');

        self::assertSame(1, $this->outboxCount(SyncLovDictionary::class));
    }

    private function testValue(): ValeurAttribut
    {
        $attribute = new AttributDefinition(self::ATTRIBUTE_ID, self::ATTRIBUTE_CODE, 'Attribut de test', translatable: true);
        $value = new ValeurAttribut(self::VALUE_ID, $attribute, self::VALUE_CODE, 'Valeur de test', 5, false);
        $this->entityManager->persist($attribute);
        $this->entityManager->persist($value);

        return $value;
    }

    private function outboxCount(string $messageType): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM outbox_message WHERE message_type = ?',
            [$messageType],
        );
    }

    private function clear(): void
    {
        $this->connection->executeStatement(
            'DELETE FROM outbox_message WHERE message_type = ?',
            [SyncLovDictionary::class],
        );
        $this->connection->executeStatement(
            'DELETE FROM pim_attribute_value_translation WHERE value_id = ?',
            [self::VALUE_ID],
        );
        $this->connection->executeStatement(
            'DELETE FROM pim_attribute_value WHERE id = ?',
            [self::VALUE_ID],
        );
        $this->connection->executeStatement(
            'DELETE FROM pim_attribute_definition WHERE id = ?',
            [self::ATTRIBUTE_ID],
        );
    }
}
