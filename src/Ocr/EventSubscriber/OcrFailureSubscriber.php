<?php

declare(strict_types=1);

namespace App\Ocr\EventSubscriber;

use App\Ocr\Entity\DocumentExtraction;
use App\Ocr\Message\ExtractDocument;
use App\Ocr\Message\CleanupBoxFile;
use App\Ocr\Repository\DocumentExtractionRepository;
use App\Ocr\Service\OcrRecoverableExtractionException;
use App\Shared\Outbox\OutboxPublisherInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Exception\HandlerFailedException;

#[AsEventListener]
final readonly class OcrFailureSubscriber
{
    public function __construct(
        private DocumentExtractionRepository $extractions,
        private EntityManagerInterface $entityManager,
        private OutboxPublisherInterface $outbox,
    ) {}

    public function __invoke(WorkerMessageFailedEvent $event): void
    {
        $message = $event->getEnvelope()->getMessage();
        if (!$message instanceof ExtractDocument) { return; }
        $extraction = $this->extractions->find($message->extractionId);
        if (!$extraction instanceof DocumentExtraction) { return; }
        foreach ($this->cleanupFiles($event->getThrowable()) as $fileId) {
            $extraction->rememberTemporaryBoxFile($fileId);
            $this->outbox->enqueue(new CleanupBoxFile($extraction->id(), $fileId));
        }
        $extraction->recordTechnicalAttempt();
        if ($event->willRetry()) { $this->entityManager->flush(); return; }
        $extraction->fail($event->getThrowable()->getMessage());
        $this->entityManager->flush();
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
