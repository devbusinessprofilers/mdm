<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use App\Shared\Messenger\AbstractWorkerFailureListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

final class AbstractWorkerFailureListenerTest extends TestCase
{
    public function testLEchecDefinitifMarqueEtFlushSurUnManagerReouvert(): void
    {
        $ferme = $this->createMock(EntityManagerInterface::class);
        $ferme->method('isOpen')->willReturn(false);
        $ferme->expects(self::never())->method('flush');
        $ouvert = $this->createMock(EntityManagerInterface::class);
        $ouvert->method('isOpen')->willReturn(true);
        $ouvert->expects(self::once())->method('flush');
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManager')->willReturn($ferme);
        $registry->expects(self::once())->method('resetManager')->willReturn($ouvert);
        $journal = new JournalDeTest();

        $marques = new \ArrayObject();
        $listener = $this->listener($registry, $journal, $marques, fn (EntityManagerInterface $manager, object $message): string => $manager === $ouvert ? 'manager réouvert' : 'mauvais manager');
        $listener($this->evenement(new \stdClass(), willRetry: false));

        self::assertSame(['manager réouvert'], $marques->getArrayCopy());
        self::assertSame([], $journal->erreurs);
    }

    public function testUneRelanceEnAttenteNEstPasMarqueeParDefaut(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects(self::never())->method('getManager');
        $marques = new \ArrayObject();
        $listener = $this->listener($registry, new JournalDeTest(), $marques);

        $listener($this->evenement(new \stdClass(), willRetry: true));

        self::assertSame([], $marques->getArrayCopy());
    }

    public function testUnMessageEtrangerEstIgnore(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects(self::never())->method('getManager');
        $marques = new \ArrayObject();
        $listener = $this->listener($registry, new JournalDeTest(), $marques);

        $listener($this->evenement(new \DateTimeImmutable(), willRetry: false));

        self::assertSame([], $marques->getArrayCopy());
    }

    public function testUneErreurPendantLeMarquageEstJournaliseeSansRelancer(): void
    {
        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->method('isOpen')->willReturn(true);
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManager')->willReturn($manager);
        $journal = new JournalDeTest();
        $listener = $this->listener($registry, $journal, new \ArrayObject(), static fn (): string => throw new \RuntimeException('base injoignable'));

        $listener($this->evenement(new \stdClass(), willRetry: false));

        self::assertCount(1, $journal->erreurs);
        self::assertStringContainsString('Impossible de marquer', $journal->erreurs[0]);
    }

    /**
     * @param \ArrayObject<int, string>                               $marques
     * @param (callable(EntityManagerInterface, object): string)|null $marquerAvec
     */
    private function listener(ManagerRegistry $registry, JournalDeTest $journal, \ArrayObject $marques, ?callable $marquerAvec = null): AbstractWorkerFailureListener
    {
        $marquer = \Closure::fromCallable($marquerAvec ?? static fn (EntityManagerInterface $manager, object $message): string => 'marqué');

        return new readonly class($registry, $journal, $marques, $marquer) extends AbstractWorkerFailureListener {
            /** @param \ArrayObject<int, string> $marques */
            public function __construct(ManagerRegistry $registry, JournalDeTest $journal, private \ArrayObject $marques, private \Closure $marquer)
            {
                parent::__construct($registry, $journal);
            }

            protected function concerne(object $message): bool
            {
                return $message instanceof \stdClass;
            }

            protected function marquer(EntityManagerInterface $manager, object $message, WorkerMessageFailedEvent $event): void
            {
                $this->marques[] = ($this->marquer)($manager, $message);
            }
        };
    }

    private function evenement(object $message, bool $willRetry): WorkerMessageFailedEvent
    {
        $event = new WorkerMessageFailedEvent(new Envelope($message), 'pim', new \RuntimeException('boum'));
        if ($willRetry) {
            $event->setForRetry();
        }

        return $event;
    }
}

final class JournalDeTest extends AbstractLogger
{
    /** @var list<string> */
    public array $erreurs = [];

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        if ('error' === $level) {
            $this->erreurs[] = (string) $message;
        }
    }
}
