<?php

declare(strict_types=1);

namespace App\Tests\Shared\Metrics;

use App\Shared\Metrics\WorkerHeartbeatReporter;
use App\Shared\Metrics\WorkerNameResolver;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class WorkerHeartbeatReporterTest extends TestCase
{
    public function testLeThrottleEspaceLesEcritures(): void
    {
        $clock = new MockClock('2026-08-27 10:00:00');
        $connection = $this->createMock(Connection::class);
        // start() force un flush ; les beat() suivants sont retenus tant que
        // 5 s ne se sont pas écoulées.
        $connection->expects(self::exactly(2))->method('executeStatement');

        $reporter = new WorkerHeartbeatReporter($connection, new WorkerNameResolver(), $clock);
        $reporter->start(['pim']);
        $reporter->beat();
        $clock->sleep(2);
        $reporter->beat();
        $clock->sleep(3.5);
        $reporter->beat();
    }

    public function testLesCompteursCumulesPartentDansLUpsert(): void
    {
        $clock = new MockClock('2026-08-27 10:00:00');
        $captures = [];
        $connection = $this->createStub(Connection::class);
        $connection->method('executeStatement')->willReturnCallback(
            static function (string $sql, array $params = []) use (&$captures): int {
                $captures[] = $params;

                return 1;
            },
        );

        $reporter = new WorkerHeartbeatReporter($connection, new WorkerNameResolver(), $clock);
        $reporter->start(['etl', 'enrichment', 'completeness', 'marketplace']);
        $reporter->recordMessage('App\Etl\Message\SyncFicheMarketplace', 'marketplace', 'handled', 1.5);
        $clock->sleep(6);
        $reporter->recordMessage('App\Etl\Message\SyncFicheMarketplace', 'marketplace', 'failed', 0.5);

        $dernier = end($captures);
        self::assertIsArray($dernier);
        self::assertSame('worker-batch', $dernier['worker_name']);
        self::assertSame(2000, $dernier['busy_ms_total']);
        self::assertSame(1, $dernier['handled_total']);
        self::assertSame(1, $dernier['failed_total']);
        $stats = json_decode((string) $dernier['message_stats'], true);
        self::assertSame(2, $stats['App\Etl\Message\SyncFicheMarketplace']['count']);
        self::assertSame(1, $stats['App\Etl\Message\SyncFicheMarketplace']['failed']);
    }

    public function testUnMessageLongEstFlusheALaReception(): void
    {
        $clock = new MockClock('2026-08-27 10:00:00');
        $captures = [];
        $connection = $this->createStub(Connection::class);
        $connection->method('executeStatement')->willReturnCallback(
            static function (string $sql, array $params = []) use (&$captures): int {
                $captures[] = $params;

                return 1;
            },
        );

        $reporter = new WorkerHeartbeatReporter($connection, new WorkerNameResolver(), $clock);
        $reporter->start(['dam']);
        $clock->sleep(2);
        $reporter->messageStarted('App\Dam\Message\RegenerateMedia');

        $dernier = end($captures);
        self::assertIsArray($dernier);
        self::assertSame('App\Dam\Message\RegenerateMedia', $dernier['current_message_class']);
    }

    public function testUnIncidentBddNeSePropagePas(): void
    {
        $clock = new MockClock('2026-08-27 10:00:00');
        $connection = $this->createStub(Connection::class);
        $connection->method('executeStatement')->willThrowException(new \RuntimeException('MySQL down'));

        $reporter = new WorkerHeartbeatReporter($connection, new WorkerNameResolver(), $clock);
        $reporter->start(['mail']);
        $reporter->stop();

        // Arriver ici sans exception est précisément le comportement attendu.
        $this->addToAssertionCount(1);
    }

    public function testLeResolveurNommeLesWorkersDocker(): void
    {
        $resolver = new WorkerNameResolver();
        self::assertSame('worker-pim', $resolver->resolve(['pim']));
        self::assertSame('worker-batch', $resolver->resolve(['etl', 'enrichment', 'completeness', 'marketplace']));
        self::assertSame('worker-batch', $resolver->resolve(['marketplace', 'etl', 'completeness', 'enrichment']));
        self::assertSame('cron-scheduler', $resolver->resolve(['scheduler_dashboard', 'scheduler_default']));
        self::assertSame('worker-outbox', $resolver->resolve(['outbox']));
        self::assertSame('worker-dam+mail', $resolver->resolve(['mail', 'dam']));
    }
}
