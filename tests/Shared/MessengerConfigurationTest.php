<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use App\Pim\Message\CalculateFicheCompleteness;
use App\Shared\Message\MediaProcessed;
use App\Shared\Message\MediaUploaded;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\EventListener\StopWorkerOnMessageLimitListener;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\Handler\HandlerDescriptor;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Retry\MultiplierRetryStrategy;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Worker;

final class MessengerConfigurationTest extends KernelTestCase
{
    private const IN_MEMORY_DSN = 'in-memory://?serialize=true';

    private ?string $previousPimDsn = null;

    protected function setUp(): void
    {
        // Ces tests vérifient la sémantique du routage avec des transports
        // in-memory ; le DSN Doctrine servant aux tests d'intégration base de
        // données (groupe "database") est restauré dans tearDown().
        $this->previousPimDsn = getenv('TEST_MESSENGER_PIM_DSN') ?: null;
        putenv('TEST_MESSENGER_PIM_DSN='.self::IN_MEMORY_DSN);
        $_ENV['TEST_MESSENGER_PIM_DSN'] = $_SERVER['TEST_MESSENGER_PIM_DSN'] = self::IN_MEMORY_DSN;
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if (null === $this->previousPimDsn) {
            putenv('TEST_MESSENGER_PIM_DSN');
            unset($_ENV['TEST_MESSENGER_PIM_DSN'], $_SERVER['TEST_MESSENGER_PIM_DSN']);
        } else {
            putenv('TEST_MESSENGER_PIM_DSN='.$this->previousPimDsn);
            $_ENV['TEST_MESSENGER_PIM_DSN'] = $_SERVER['TEST_MESSENGER_PIM_DSN'] = $this->previousPimDsn;
        }
    }

    public function testMediaUploadedIsSerializedAndRoutedToDam(): void
    {
        $bus = $this->messageBus();
        $dam = $this->transport('dam');

        $bus->dispatch(new MediaUploaded(
            mediaId: 'media-42',
            storageKey: 'originals/media-42.jpg',
            checksum: 'sha256-checksum',
            expectedRenditions: ['thumbnail', 'web'],
        ));

        self::assertCount(1, $dam->getSent());
        self::assertSame([], $this->transport('pim')->getSent());

        $message = $dam->getSent()[0]->getMessage();
        self::assertInstanceOf(MediaUploaded::class, $message);
        self::assertSame('media-42', $message->mediaId);
        self::assertSame(['thumbnail', 'web'], $message->expectedRenditions);
    }

    public function testMediaProcessedIsSerializedAndRoutedToPim(): void
    {
        $bus = $this->messageBus();
        $pim = $this->transport('pim');

        $bus->dispatch(new MediaProcessed(
            mediaId: 'media-42',
            renditionUrls: ['thumbnail' => 'https://cdn.example.test/media-42.webp'],
            tags: ['hotel'],
            status: 'processed',
            duplicateOf: null,
        ));

        self::assertCount(1, $pim->getSent());
        self::assertSame([], $this->transport('dam')->getSent());

        $message = $pim->getSent()[0]->getMessage();
        self::assertInstanceOf(MediaProcessed::class, $message);
        self::assertSame('media-42', $message->mediaId);
        self::assertSame('processed', $message->status);
    }

    public function testCompletenessMessagesUseTheDedicatedTransport(): void
    {
        $this->messageBus()->dispatch(new CalculateFicheCompleteness('01KYW9WA9P1D5QG5PDHCQC1JQV'));

        self::assertCount(1, $this->transport('completeness')->getSent());
        self::assertSame([], $this->transport('pim')->getSent());
    }

    /**
     * @param list<int> $expectedDelays
     */
    #[DataProvider('retryPolicyProvider')]
    public function testRetryPolicy(
        string $transportName,
        array $expectedDelays,
    ): void {
        self::bootKernel();
        $strategy = self::getContainer()->get('messenger.retry.multiplier_retry_strategy.'.$transportName);
        self::assertInstanceOf(MultiplierRetryStrategy::class, $strategy);

        foreach ($expectedDelays as $retryCount => $expectedDelay) {
            $envelope = new Envelope(new \stdClass(), [new RedeliveryStamp($retryCount)]);

            self::assertTrue($strategy->isRetryable($envelope));
            self::assertEqualsWithDelta($expectedDelay, $strategy->getWaitingTime($envelope), $expectedDelay * 0.1);
        }

        $exhausted = new Envelope(new \stdClass(), [new RedeliveryStamp(5)]);
        self::assertFalse($strategy->isRetryable($exhausted));
    }

    public function testTemporaryFailureSchedulesARetryOnTheOriginalTransport(): void
    {
        $pim = $this->transport('pim');

        $this->runFailingWorker(new \RuntimeException('Temporary failure'));

        self::assertCount(2, $pim->getSent());
        self::assertCount(1, $pim->getRejected());
        self::assertSame([], $this->transport('failed')->getSent());
        self::assertNotNull($pim->getSent()[1]->last(DelayStamp::class));
        self::assertSame(0, RedeliveryStamp::getRetryCountFromEnvelope($pim->getSent()[0]));
        self::assertSame(1, RedeliveryStamp::getRetryCountFromEnvelope($pim->getSent()[1]));
    }

    public function testPermanentFailureIsMovedToFailureTransportWithoutRetry(): void
    {
        $pim = $this->transport('pim');

        $this->runFailingWorker(new UnrecoverableMessageHandlingException('Permanent failure'));

        self::assertCount(1, $pim->getSent());
        self::assertCount(1, $pim->getRejected());
        self::assertCount(1, $this->transport('failed')->getSent());
    }

    public function testExhaustedRetriesMoveTheMessageToFailureTransport(): void
    {
        $pim = $this->transport('pim');

        $this->runFailingWorker(new \RuntimeException('Still failing'), retryCount: 5);

        self::assertCount(1, $pim->getSent());
        self::assertCount(1, $pim->getRejected());
        self::assertCount(1, $this->transport('failed')->getSent());
    }

    /**
     * @return iterable<string, array{string, list<int>}>
     */
    public static function retryPolicyProvider(): iterable
    {
        yield 'pim' => ['pim', [1000, 2000, 4000, 8000, 16000]];
        yield 'dam' => ['dam', [5000, 10000, 20000, 40000, 60000]];
        yield 'etl' => ['etl', [10000, 20000, 40000, 60000, 60000]];
        yield 'enrichment' => ['enrichment', [10000, 20000, 40000, 60000, 60000]];
        yield 'completeness' => ['completeness', [1000, 2000, 4000, 8000, 16000]];
        yield 'mail' => ['mail', [10000, 20000, 40000, 60000, 60000]];
    }

    private function messageBus(): MessageBusInterface
    {
        self::bootKernel();

        return self::getContainer()->get(MessageBusInterface::class);
    }

    private function transport(string $name): InMemoryTransport
    {
        $transport = self::getContainer()->get('messenger.transport.'.$name);
        self::assertInstanceOf(InMemoryTransport::class, $transport);

        return $transport;
    }

    private function runFailingWorker(\Throwable $failure, int $retryCount = 0): void
    {
        $message = new MediaUploaded(
            mediaId: 'media-failure',
            storageKey: 'originals/media-failure.jpg',
            checksum: 'sha256-checksum',
            expectedRenditions: ['web'],
        );
        $pim = $this->transport('pim');
        $stamps = $retryCount > 0 ? [new RedeliveryStamp($retryCount)] : [];
        $pim->send(new Envelope($message, $stamps));

        $handler = static function () use ($failure): never {
            throw $failure;
        };
        $bus = new MessageBus([
            new HandleMessageMiddleware(new HandlersLocator([
                MediaUploaded::class => [new HandlerDescriptor($handler)],
            ])),
        ]);
        $eventDispatcher = self::getContainer()->get('event_dispatcher');
        self::assertInstanceOf(EventDispatcherInterface::class, $eventDispatcher);
        $eventDispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(1));

        $worker = new Worker(['pim' => $pim], $bus, $eventDispatcher);
        $worker->run(['sleep' => 0]);
    }
}
