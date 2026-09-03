<?php

declare(strict_types=1);

namespace App\Tests\Dashboard;

use App\Dashboard\Entity\DashboardSnapshot;
use App\Dashboard\Message\ComputeDashboardStats;
use App\Dashboard\MessageHandler\ComputeDashboardStatsHandler;
use App\Dashboard\Repository\DashboardSnapshotRepository;
use App\Dashboard\Service\DashboardStatsCalculator;
use App\Tests\Support\CommeUnWorker;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Ulid;

#[Group('database')]
final class ComputeDashboardStatsHandlerTest extends KernelTestCase
{
    private Connection $connection;
    private EntityManagerInterface $entityManager;
    private DashboardSnapshotRepository $repository;
    private ComputeDashboardStatsHandler $handler;

    protected function setUp(): void
    {
        if (!str_starts_with((string) getenv('TEST_MESSENGER_PIM_DSN'), 'doctrine://')) {
            self::markTestSkipped('Set TEST_MESSENGER_PIM_DSN to a Doctrine transport to run database integration tests.');
        }

        self::bootKernel();
        $this->connection = self::getContainer()->get(Connection::class);
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->repository = self::getContainer()->get(DashboardSnapshotRepository::class);
        $this->handler = new ComputeDashboardStatsHandler(
            new DashboardStatsCalculator($this->entityManager),
            $this->repository,
            $this->entityManager,
            new NullLogger(),
        );
        $this->connection->executeStatement('DELETE FROM dashboard_snapshot');
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->connection->executeStatement('DELETE FROM dashboard_snapshot');
        }

        parent::tearDown();
    }

    public function testHandlerPersistsSnapshotReturnedByLatest(): void
    {
        CommeUnWorker::traiter($this->entityManager, $this->handler, new ComputeDashboardStats());

        $latest = $this->repository->latest();
        self::assertNotNull($latest);
        self::assertSame(DashboardSnapshot::SCHEMA_VERSION, $latest->schemaVersion());
        $payload = $latest->payload();
        self::assertArrayHasKey('fiches', $payload);
        self::assertArrayHasKey('countryByType', $payload);
        self::assertArrayHasKey('perUser', $payload);
    }

    public function testHandlerCompactsHistoryOlderThanSevenDaysToOnePerDay(): void
    {
        // Trois snapshots le même jour il y a dix jours, un snapshot récent.
        $oldDay = new \DateTimeImmutable('-10 days');
        foreach (['08:00:00', '12:00:00', '18:00:00'] as $time) {
            $this->insertSnapshot($oldDay->format('Y-m-d').' '.$time);
        }
        $this->insertSnapshot(new \DateTimeImmutable('-1 hour')->format('Y-m-d H:i:s'));

        CommeUnWorker::traiter($this->entityManager, $this->handler, new ComputeDashboardStats());

        $oldCount = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM dashboard_snapshot WHERE DATE(computed_at) = ?',
            [$oldDay->format('Y-m-d')],
        );
        self::assertSame(1, $oldCount, 'Seul le dernier snapshot du jour doit être conservé.');
        $kept = $this->connection->fetchOne(
            'SELECT computed_at FROM dashboard_snapshot WHERE DATE(computed_at) = ?',
            [$oldDay->format('Y-m-d')],
        );
        self::assertStringContainsString('18:00:00', (string) $kept);
        // Le snapshot récent et celui créé par le handler restent intacts.
        self::assertSame(3, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM dashboard_snapshot'));
    }

    private function insertSnapshot(string $computedAt): void
    {
        $this->connection->executeStatement(
            'INSERT INTO dashboard_snapshot (id, schema_version, payload, computed_at, duration_ms) VALUES (?, 1, ?, ?, 0)',
            [new Ulid()->toBinary(), '{}', $computedAt],
        );
    }
}
