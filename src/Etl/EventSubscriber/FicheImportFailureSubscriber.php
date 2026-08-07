<?php

declare(strict_types=1);

namespace App\Etl\EventSubscriber;

use App\Etl\Entity\FicheImportJob;
use App\Etl\Enum\ImportJobStatus;
use App\Etl\Message\ProcessFicheImportBatch;
use App\Etl\Repository\FicheImportJobRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Uid\Ulid;

/**
 * Le handler ne gère que les erreurs métier (RowOutcome) : une exception
 * imprévue part en retries puis en queue failed, et sans ce listener le job
 * resterait « EnCours » pour toujours côté UI.
 */
#[AsEventListener]
final readonly class FicheImportFailureSubscriber
{
    public function __construct(
        private FicheImportJobRepository $jobs,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(WorkerMessageFailedEvent $event): void
    {
        if ($event->willRetry()) {
            return;
        }
        $message = $event->getEnvelope()->getMessage();
        if (!$message instanceof ProcessFicheImportBatch || !Ulid::isValid($message->jobId)) {
            return;
        }
        $job = $this->jobs->find(Ulid::fromString($message->jobId));
        if (!$job instanceof FicheImportJob || ImportJobStatus::EnCours !== $job->status()) {
            return;
        }
        $job->fail(sprintf(
            'Import interrompu (lot à partir de la ligne %d) : %s',
            $message->fromLine,
            $event->getThrowable()->getMessage(),
        ));
        $this->entityManager->flush();
    }
}
