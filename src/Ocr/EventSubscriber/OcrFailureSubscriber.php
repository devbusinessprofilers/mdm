<?php

declare(strict_types=1);

namespace App\Ocr\EventSubscriber;

use App\Ocr\Entity\DocumentExtraction;
use App\Ocr\Message\ExtractDocument;
use App\Ocr\Message\CleanupBoxFile;
use App\Ocr\Service\OcrRecoverableExtractionException;
use App\Shared\Outbox\OutboxPublisherInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Exception\HandlerFailedException;

#[AsEventListener]
final readonly class OcrFailureSubscriber
{
    public function __construct(
        private ManagerRegistry $registry,
        private OutboxPublisherInterface $outbox,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(WorkerMessageFailedEvent $event): void
    {
        $message = $event->getEnvelope()->getMessage();
        if (!$message instanceof ExtractDocument) { return; }
        try {
            // L'échec du handler peut avoir fermé l'EntityManager (exception
            // Doctrine) : le réinitialiser avant de marquer l'extraction.
            $manager = $this->registry->getManagerForClass(DocumentExtraction::class);
            if (!$manager instanceof EntityManagerInterface || !$manager->isOpen()) { $manager = $this->registry->resetManager(); }
            $extraction = $manager->find(DocumentExtraction::class, $message->extractionId);
            if (!$extraction instanceof DocumentExtraction) { return; }
            foreach ($this->cleanupFiles($event->getThrowable()) as $fileId) {
                $extraction->rememberTemporaryBoxFile($fileId);
                $this->outbox->enqueue(new CleanupBoxFile($extraction->id(), $fileId));
            }
            $extraction->recordTechnicalAttempt();
            if ($event->willRetry()) { $manager->flush(); return; }
            $extraction->fail($event->getThrowable()->getMessage());
            $manager->flush();
        } catch (\Throwable $error) {
            // Dernier recours : un subscriber d'échec ne doit jamais relancer.
            $this->logger->error('Impossible de marquer l’extraction OCR en échec.', ['extraction_id' => $message->extractionId, 'exception' => $error]);
        }
    }

    /** @return list<string> */
    private function cleanupFiles(\Throwable $error): array
    {
        if ($error instanceof OcrRecoverableExtractionException) { return $error->boxFilesToClean; }
        if ($error instanceof HandlerFailedException) {
            $files = [];
            foreach ($error->getWrappedExceptions() as $wrapped) { $files = [...$files, ...$this->cleanupFiles($wrapped)]; }
            return array_values(array_unique($files));
        }
        return null === $error->getPrevious() ? [] : $this->cleanupFiles($error->getPrevious());
    }
}
