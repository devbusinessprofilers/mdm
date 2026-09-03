<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use App\Shared\Message\MediaProcessed;
use App\Shared\Message\MediaUploaded;
use App\Shared\Outbox\EventIdStamp;
use App\Shared\Outbox\IdempotencyMiddleware;
use App\Shared\Outbox\OutboxPublisher;
use App\Shared\Outbox\OutboxPublisherInterface;
use App\Shared\Outbox\OutboxRelay;
use App\Shared\Outbox\OutboxRepository;
use App\Shared\Outbox\ReceivedDoctrineTransactionMiddleware;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Handler\HandlerDescriptor;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

#[Group('database')]
final class OutboxIntegrationTest extends KernelTestCase
{
    private Connection $connection;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        if (!str_starts_with((string) getenv('TEST_MESSENGER_PIM_DSN'), 'doctrine://')) {
            self::markTestSkipped('Set TEST_MESSENGER_PIM_DSN to a Doctrine transport to run database integration tests.');
        }

        self::bootKernel();
        $this->connection = self::getContainer()->get(Connection::class);
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS outbox_test_effect (event_id CHAR(26) NOT NULL, PRIMARY KEY(event_id)) ENGINE = InnoDB',
        );
        $this->clearTables();
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->clearTables();
        }

        parent::tearDown();
    }

    public function testEnqueueJoinsTheCurrentDoctrineUnitOfWork(): void
    {
        $publisher = $this->publisher();
        $eventId = $publisher->enqueue($this->processedMessage());

        self::assertSame(0, $this->countRows('outbox_message'));

        $this->entityManager->flush();
        $row = $this->connection->fetchAssociative('SELECT * FROM outbox_message WHERE id = ?', [$eventId]);

        self::assertIsArray($row);
        self::assertSame('pending', $row['status']);
        self::assertSame(MediaProcessed::class, $row['message_type']);
        $headers = json_decode((string) $row['headers'], true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($headers);
        self::assertArrayHasKey('X-Message-Stamp-'.EventIdStamp::class, $headers);
        self::assertSame(0, (int) $row['attempts']);
    }

    public function testRollbackRemovesTheBusinessWriteAndOutboxTogether(): void
    {
        $this->connection->beginTransaction();
        $this->connection->insert('outbox_test_effect', ['event_id' => '01KTESTROLLBACK00000000000']);
        $this->publisher()->enqueue($this->processedMessage());
        $this->entityManager->flush();
        $this->connection->rollBack();
        $this->entityManager->clear();

        self::assertSame(0, $this->countRows('outbox_test_effect'));
        self::assertSame(0, $this->countRows('outbox_message'));
    }

    public function testRelayPublishesAndMarksTheOutboxAtomically(): void
    {
        $eventId = $this->publisher()->enqueue($this->processedMessage());
        $this->entityManager->flush();

        $result = $this->relay()->relayBatch('test-worker');

        self::assertSame(1, $result->claimed);
        self::assertSame(1, $result->published);
        self::assertSame(0, $result->failed);
        self::assertSame('published', $this->connection->fetchOne('SELECT status FROM outbox_message WHERE id = ?', [$eventId]));
        self::assertSame(1, (int) $this->connection->fetchOne("SELECT COUNT(*) FROM messenger_messages WHERE queue_name = 'pim'"));
    }

    public function testMalformedPayloadUsesBackoffThenMovesToFailed(): void
    {
        $eventId = $this->publisher()->enqueue($this->processedMessage());
        $this->entityManager->flush();
        $this->connection->update('outbox_message', ['body' => '{invalid-json'], ['id' => $eventId]);

        for ($attempt = 1; $attempt <= 5; ++$attempt) {
            $result = $this->relay()->relayBatch('test-worker');
            self::assertSame(1, $result->failed);

            $row = $this->connection->fetchAssociative('SELECT status, attempts, last_error FROM outbox_message WHERE id = ?', [$eventId]);
            self::assertIsArray($row);
            self::assertSame($attempt, (int) $row['attempts']);
            self::assertNotEmpty($row['last_error']);
            self::assertSame(5 === $attempt ? 'failed' : 'pending', $row['status']);

            $this->connection->update('outbox_message', ['available_at' => '2000-01-01 00:00:00'], ['id' => $eventId]);
        }

        self::assertTrue($this->repository()->retryFailed($eventId));
        self::assertSame('pending', $this->connection->fetchOne('SELECT status FROM outbox_message WHERE id = ?', [$eventId]));
    }

    public function testMalformedHeadersAreRecordedWithoutCrashingTheWorker(): void
    {
        $eventId = $this->publisher()->enqueue($this->processedMessage());
        $this->entityManager->flush();
        $this->connection->update('outbox_message', ['headers' => '[]'], ['id' => $eventId]);

        $result = $this->relay()->relayBatch('test-worker');
        $row = $this->connection->fetchAssociative('SELECT status, attempts, locked_at, last_error FROM outbox_message WHERE id = ?', [$eventId]);

        self::assertSame(1, $result->failed);
        self::assertIsArray($row);
        self::assertSame('pending', $row['status']);
        self::assertSame(1, (int) $row['attempts']);
        self::assertNull($row['locked_at']);
        self::assertNotEmpty($row['last_error']);
    }

    public function testConcurrentWorkerSkipsALockedRowAndCanRecoverItsLease(): void
    {
        $eventId = $this->publisher()->enqueue($this->processedMessage());
        $this->entityManager->flush();
        $secondConnection = DriverManager::getConnection($this->connection->getParams());

        try {
            $this->connection->beginTransaction();
            $this->connection->fetchOne('SELECT id FROM outbox_message WHERE id = ? FOR UPDATE', [$eventId]);
            $secondRepository = new OutboxRepository($secondConnection);

            self::assertNull($secondRepository->claimNext('worker-2'));
            $this->connection->rollBack();

            $secondRepository = new OutboxRepository($secondConnection);
            $record = $secondRepository->claimNext('worker-2');
            self::assertNotNull($record);
            self::assertSame($eventId, $record->id);

            $secondConnection->update('outbox_message', ['locked_at' => '2000-01-01 00:00:00'], ['id' => $eventId]);
            self::assertNotNull($this->repository()->claimNext('worker-1'));
        } finally {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }
            $secondConnection->close();
        }
    }

    public function testIdempotencyReceiptAndHandlerEffectsShareOneTransaction(): void
    {
        $eventId = '01KTESTIDEMPOTENT000000000';
        $envelope = new Envelope(
            new MediaUploaded('media-1', 'original.jpg', 'checksum', ['web']),
            [new EventIdStamp($eventId), new ReceivedStamp('dam')],
        );
        $successfulBus = $this->handlerBus(static function (MediaUploaded $message) use ($eventId): void {
            unset($message);
            self::getContainer()->get(Connection::class)->insert('outbox_test_effect', ['event_id' => $eventId]);
        });

        $successfulBus->dispatch($envelope);
        $successfulBus->dispatch($envelope);

        self::assertSame(1, $this->countRows('outbox_test_effect'));
        self::assertSame(1, $this->countRows('processed_message'));
    }

    public function testHandlerFailureRollsBackItsReceiptAndCanBeRetried(): void
    {
        $eventId = '01KTESTRETRY0000000000000';
        $envelope = new Envelope(
            new MediaUploaded('media-2', 'original.jpg', 'checksum', ['web']),
            [new EventIdStamp($eventId), new ReceivedStamp('dam')],
        );
        $failingBus = $this->handlerBus(static function (MediaUploaded $message) use ($eventId): never {
            unset($message);
            self::getContainer()->get(Connection::class)->insert('outbox_test_effect', ['event_id' => $eventId]);
            throw new \RuntimeException('Handler failure');
        });

        try {
            $failingBus->dispatch($envelope);
            self::fail('The handler should have failed.');
        } catch (HandlerFailedException) {
        }

        self::assertSame(0, $this->countRows('outbox_test_effect'));
        self::assertSame(0, $this->countRows('processed_message'));

        $this->handlerBus(static function (MediaUploaded $message) use ($eventId): void {
            unset($message);
            self::getContainer()->get(Connection::class)->insert('outbox_test_effect', ['event_id' => $eventId]);
        })->dispatch($envelope);

        self::assertSame(1, $this->countRows('outbox_test_effect'));
        self::assertSame(1, $this->countRows('processed_message'));
    }

    public function testPurgeKeepsRecentAndUnpublishedRows(): void
    {
        $oldPublished = $this->publisher()->enqueue($this->processedMessage());
        $recentPublished = $this->publisher()->enqueue($this->processedMessage());
        $pending = $this->publisher()->enqueue($this->processedMessage());
        $this->entityManager->flush();
        $this->connection->executeStatement(
            "UPDATE outbox_message SET status = 'published', published_at = '2020-01-01 00:00:00' WHERE id = ?",
            [$oldPublished],
        );
        $this->connection->executeStatement(
            "UPDATE outbox_message SET status = 'published', published_at = NOW() WHERE id = ?",
            [$recentPublished],
        );
        $this->connection->insert('processed_message', [
            'event_id' => '01KTESTOLDRECEIPT00000000',
            'message_type' => MediaProcessed::class,
            'processed_at' => '2020-01-01 00:00:00',
        ]);
        $this->connection->insert('processed_message', [
            'event_id' => '01KTESTNEWRECEIPT00000000',
            'message_type' => MediaProcessed::class,
            'processed_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        $result = $this->repository()->purge(new \DateTimeImmutable('-30 days'), new \DateTimeImmutable('-180 days'));

        self::assertSame(['outbox' => 1, 'receipts' => 1], $result);
        self::assertSame(2, $this->countRows('outbox_message'));
        self::assertSame(1, $this->countRows('processed_message'));
        self::assertSame('pending', $this->connection->fetchOne('SELECT status FROM outbox_message WHERE id = ?', [$pending]));
    }

    public function testUnroutedMessageIsRejectedBeforePersistence(): void
    {
        $this->expectException(\LogicException::class);
        $this->publisher()->enqueue(new \stdClass());
    }

    private function publisher(): OutboxPublisherInterface
    {
        return self::getContainer()->get(OutboxPublisher::class);
    }

    private function relay(): OutboxRelay
    {
        return self::getContainer()->get(OutboxRelay::class);
    }

    private function repository(): OutboxRepository
    {
        return self::getContainer()->get(OutboxRepository::class);
    }

    private function processedMessage(): MediaProcessed
    {
        return new MediaProcessed('media-42', ['web' => 'https://cdn.example.test/web.webp'], ['hotel'], 'processed');
    }

    /**
     * @param callable(MediaUploaded): void $handler
     */
    private function handlerBus(callable $handler): MessageBus
    {
        return new MessageBus([
            self::getContainer()->get(ReceivedDoctrineTransactionMiddleware::class),
            self::getContainer()->get(IdempotencyMiddleware::class),
            new HandleMessageMiddleware(new HandlersLocator([
                MediaUploaded::class => [new HandlerDescriptor($handler)],
            ])),
        ]);
    }

    private function countRows(string $table): int
    {
        return (int) $this->connection->fetchOne(sprintf('SELECT COUNT(*) FROM %s', $table));
    }

    private function clearTables(): void
    {
        $this->entityManager->clear();
        $this->connection->executeStatement('DELETE FROM messenger_messages');
        $this->connection->executeStatement('DELETE FROM processed_message');
        $this->connection->executeStatement('DELETE FROM outbox_message');
        $this->connection->executeStatement('DELETE FROM outbox_test_effect');
    }
}
