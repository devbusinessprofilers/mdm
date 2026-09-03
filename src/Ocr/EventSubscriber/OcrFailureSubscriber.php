<?php

declare(strict_types=1);

namespace App\Ocr\EventSubscriber;

use App\Ocr\Entity\DocumentExtraction;
use App\Ocr\Message\CleanupBoxFile;
use App\Ocr\Message\ExtractDocument;
use App\Ocr\Service\OcrRecoverableExtractionException;
use App\Shared\Messenger\AbstractWorkerFailureListener;
use App\Shared\Outbox\OutboxPublisherInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Exception\HandlerFailedException;

/**
 * Chaque tentative d'extraction est comptée sur l'extraction (et ses fichiers
 * Box temporaires planifiés au nettoyage) ; la dernière la passe en échec.
 */
#[AsEventListener]
final readonly class OcrFailureSubscriber extends AbstractWorkerFailureListener
{
    public function __construct(
        ManagerRegistry $registry,
        LoggerInterface $logger,
        private OutboxPublisherInterface $outbox,
    ) {
        parent::__construct($registry, $logger);
    }

    protected function concerne(object $message): bool
    {
        return $message instanceof ExtractDocument;
    }

    protected function agitAussiPendantLesRelances(): bool
    {
        return true;
    }

    protected function marquer(EntityManagerInterface $manager, object $message, WorkerMessageFailedEvent $event): void
    {
        /** @var ExtractDocument $message */
        $extraction = $manager->find(DocumentExtraction::class, $message->extractionId);
        if (!$extraction instanceof DocumentExtraction) {
            return;
        }
        foreach (self::cleanupFiles($event->getThrowable()) as $fileId) {
            $extraction->rememberTemporaryBoxFile($fileId);
            $this->outbox->enqueue(new CleanupBoxFile($extraction->id(), $fileId));
        }
        $extraction->recordTechnicalAttempt();
        if (!$event->willRetry()) {
            $extraction->fail($event->getThrowable()->getMessage());
        }
    }

    /** @return list<string> */
    private static function cleanupFiles(\Throwable $error): array
    {
        if ($error instanceof OcrRecoverableExtractionException) {
            return $error->boxFilesToClean;
        }
        if ($error instanceof HandlerFailedException) {
            $files = [];
            foreach ($error->getWrappedExceptions() as $wrapped) {
                $files = [...$files, ...self::cleanupFiles($wrapped)];
            }

            return array_values(array_unique($files));
        }

        return null === $error->getPrevious() ? [] : self::cleanupFiles($error->getPrevious());
    }
}
