<?php

declare(strict_types=1);

namespace App\Tests\Dashboard;

use App\Audit\Entity\AuditChange;
use App\Audit\Entity\AuditRevision;
use App\Dashboard\Service\DashboardStatsCalculator;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Localisation;
use App\Pim\Entity\Restaurant\Restaurant;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('database')]
final class DashboardStatsCalculatorTest extends KernelTestCase
{
    private Connection $connection;
    private EntityManagerInterface $entityManager;
    private DashboardStatsCalculator $calculator;

    protected function setUp(): void
    {
        if (!str_starts_with((string) getenv('TEST_MESSENGER_PIM_DSN'), 'doctrine://')) {
            self::markTestSkipped('Set TEST_MESSENGER_PIM_DSN to a Doctrine transport to run database integration tests.');
        }

        self::bootKernel();
        $this->connection = self::getContainer()->get(Connection::class);
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->calculator = new DashboardStatsCalculator($this->entityManager);
        $this->clearTables();
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->clearTables();
        }

        parent::tearDown();
    }

    public function testComputeOnEmptyDatabase(): void
    {
        $payload = $this->calculator->compute();

        self::assertSame(['total' => 0, 'published' => 0, 'publishedPct' => 0.0], $payload['fiches']);
        self::assertNull($payload['completeness']['avgGlobal']);
        self::assertSame([], $payload['countryByType']);
        self::assertSame(['avgSeconds' => null, 'sample' => 0], $payload['validation']);
        self::assertSame(0, $payload['thisWeek']['updates']);
        self::assertSame(0, $payload['thisWeek']['created']);
        self::assertSame([], $payload['perUser']['created']);
        self::assertSame([], $payload['perUser']['fieldsUpdated']);
        self::assertSame([], $payload['perUser']['validated']);
    }

    public function testStorageAggregatesActiveMediaAndRenditions(): void
    {
        $image = $this->createAsset(1000);
        $image->addRendition(new \App\Dam\Entity\MediaRendition($image, 'large', 'k/'.$image->id().'/large.webp', 960, 480, 300));
        $deleted = $this->createAsset(999);
        $deleted->markDeleted();
        $this->entityManager->persist($image);
        $this->entityManager->persist($deleted);
        $this->entityManager->flush();

        $storage = $this->calculator->compute()['storage'];

        self::assertSame(['count' => 1, 'bytes' => 1000], $storage['byKind']['image']);
        self::assertSame(['count' => 1, 'bytes' => 300], $storage['renditions']);
        self::assertSame(1300, $storage['totalBytes']);
    }

    private function createAsset(int $sizeBytes): \App\Dam\Entity\MediaAsset
    {
        $id = new \Symfony\Component\Uid\Ulid();

        return new \App\Dam\Entity\MediaAsset($id, 'originals/'.$id, 'photo.jpg', 'image/jpeg', $sizeBytes, sha1((string) $id));
    }

    public function testComputeAggregatesFichesAuditAndValidation(): void
    {
        $lieuPublie = $this->createLieu('Palais Alpha', 'FR', 'France');
        $lieuEnCours = $this->createLieu('Château Beta', 'FR', 'France');
        $restaurant = new Restaurant();
        $restaurant->changeLabel('Bistrot Gamma');
        $this->entityManager->persist($restaurant);
        $this->entityManager->flush();

        $lieuPublie->fiche()->submitForValidation('alice');
        $lieuPublie->fiche()->validateAndPublish('bob');
        $this->entityManager->flush();

        // Cycle de validation déterministe : 2 h entre demande et validation.
        $this->connection->executeStatement(
            'UPDATE pim_fiche SET validation_requested_at = ?, validation_reviewed_at = ? WHERE id = ?',
            ['2026-08-03 08:00:00', '2026-08-03 10:00:00', $lieuPublie->fiche()->id()->toBinary()],
        );
        $this->connection->executeStatement(
            'UPDATE pim_lieu SET completeness_global = 80 WHERE id = ?',
            [$lieuPublie->fiche()->id()->toBinary()],
        );
        $this->connection->executeStatement(
            'UPDATE pim_lieu SET completeness_global = 40 WHERE id = ?',
            [$lieuEnCours->fiche()->id()->toBinary()],
        );

        $this->connection->executeStatement('DELETE FROM audit_change');
        $this->connection->executeStatement('DELETE FROM audit_revision');
        $this->addRevision($lieuPublie->id(), 'create', 'alice');
        $this->addRevision($lieuEnCours->id(), 'create', 'alice');
        $this->addRevision($restaurant->id(), 'create', 'system');
        $updateByAlice = $this->addRevision($lieuPublie->id(), 'update', 'alice');
        new AuditChange($updateByAlice, 'label', 'a', 'b');
        new AuditChange($updateByAlice, 'description', null, 'c');
        $updateByBob = $this->addRevision($lieuEnCours->id(), 'update', 'bob');
        new AuditChange($updateByBob, 'label', 'x', 'y');
        $this->addRevision($lieuPublie->id(), 'publication', 'bob');
        $this->entityManager->flush();

        $payload = $this->calculator->compute();

        self::assertSame(
            ['total' => 3, 'published' => 1, 'publishedPct' => 33.3],
            $payload['fiches'],
        );
        self::assertSame(40.0, $payload['completeness']['avgGlobal']);

        self::assertCount(2, $payload['countryByType']);
        [$france, $sansPays] = $payload['countryByType'];
        self::assertSame('FR', $france['countryCode']);
        self::assertSame('France', $france['pays']);
        self::assertSame(2, $france['counts']['lieu']);
        self::assertSame(0, $france['counts']['restaurant']);
        self::assertSame(2, $france['total']);
        self::assertSame('??', $sansPays['countryCode']);
        self::assertSame(1, $sansPays['counts']['restaurant']);

        self::assertSame(['avgSeconds' => 7200, 'sample' => 1], $payload['validation']);

        self::assertSame(3, $payload['thisWeek']['created']);
        self::assertSame(2, $payload['thisWeek']['updates']);

        self::assertSame(
            [['actor' => 'alice', 'count' => 2]],
            $payload['perUser']['created'],
        );
        self::assertSame(
            [['actor' => 'alice', 'count' => 2], ['actor' => 'bob', 'count' => 1]],
            $payload['perUser']['fieldsUpdated'],
        );
        self::assertSame(
            [['actor' => 'bob', 'count' => 1]],
            $payload['perUser']['validated'],
        );
    }

    private function createLieu(string $label, string $countryCode, string $pays): Lieu
    {
        $lieu = new Lieu();
        $lieu->changeLabel($label);
        $localisation = new Localisation();
        $localisation->changeCountryCode($countryCode);
        $localisation->changePays($pays);
        $lieu->changeLocalisation($localisation);
        $this->entityManager->persist($lieu);

        return $lieu;
    }

    private function addRevision(string $ficheId, string $action, string $actor): AuditRevision
    {
        $revision = new AuditRevision($ficheId, $action, 'test', $actor, [], 'corr-'.uniqid());
        $this->entityManager->persist($revision);

        return $revision;
    }

    private function clearTables(): void
    {
        $this->connection->executeStatement('DELETE FROM dam_media_duplicate_alert');
        $this->connection->executeStatement('DELETE FROM dam_media_phash_band');
        $this->connection->executeStatement('DELETE FROM dam_media_rendition');
        $this->connection->executeStatement('DELETE FROM dam_media_asset');
        $this->connection->executeStatement('DELETE FROM audit_change');
        $this->connection->executeStatement('DELETE FROM audit_revision');
        $this->connection->executeStatement('DELETE FROM dashboard_snapshot');
        $this->connection->executeStatement('DELETE FROM pim_fiche_search');
        $this->connection->executeStatement('DELETE FROM pim_fiche_attribute_value');
        $this->connection->executeStatement('DELETE FROM pim_lieu_administratif');
        $this->connection->executeStatement('DELETE FROM pim_lieu_tarification');
        $this->connection->executeStatement('DELETE FROM pim_activite');
        $this->connection->executeStatement('DELETE FROM pim_restaurant');
        $this->connection->executeStatement('DELETE FROM pim_service_evenementiel');
        $this->connection->executeStatement('DELETE FROM pim_lieu');
        $this->connection->executeStatement('DELETE FROM pim_fiche');
        $this->connection->executeStatement('DELETE FROM pim_localisation');
    }
}
