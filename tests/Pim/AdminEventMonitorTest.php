<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Service\AdminEventMonitor;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Ulid;
use Symfony\Contracts\Cache\CacheInterface;

#[Group('database')]
final class AdminEventMonitorTest extends KernelTestCase
{
    private Connection $connection;
    private AdminEventMonitor $monitor;
    private CacheInterface $cache;

    protected function setUp(): void
    {
        if (!str_starts_with((string) getenv('TEST_MESSENGER_PIM_DSN'), 'doctrine://')) {
            self::markTestSkipped('Set TEST_MESSENGER_PIM_DSN to a Doctrine transport to run database integration tests.');
        }

        self::bootKernel();
        $this->connection = self::getContainer()->get(Connection::class);
        $this->monitor = self::getContainer()->get(AdminEventMonitor::class);
        $this->cache = self::getContainer()->get('cache.app');
        $this->clearState();
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->clearState();
        }

        parent::tearDown();
    }

    public function testSnapshotIsCachedUntilRefreshed(): void
    {
        $initial = $this->monitor->snapshot();
        self::assertTrue($initial['available']);
        self::assertSame(0, $initial['outbox']['pending'] ?? 0);

        $this->insertPendingOutboxMessage();

        $cached = $this->monitor->snapshot();
        self::assertSame(0, $cached['outbox']['pending'] ?? 0, 'Snapshot must come from the cache within the TTL.');

        $fresh = $this->monitor->snapshot(fresh: true);
        self::assertSame(1, $fresh['outbox']['pending'] ?? 0, 'snapshot(fresh: true) must bypass the cache.');
        self::assertCount(1, $fresh['recent']);
    }

    private function insertPendingOutboxMessage(): void
    {
        $this->connection->executeStatement(
            "INSERT INTO outbox_message (id, message_type, body, headers, status, attempts, occurred_at, available_at)
             VALUES (:id, 'App\\\\Pim\\\\Message\\\\IndexFiche', '{}', '{}', 'pending', 0, NOW(), NOW())",
            ['id' => (string) new Ulid()],
        );
    }

    private function clearState(): void
    {
        $this->connection->executeStatement('DELETE FROM outbox_message');
        $this->connection->executeStatement('DELETE FROM processed_message');
        $this->cache->delete('admin_event_monitor.snapshot');
    }
}
