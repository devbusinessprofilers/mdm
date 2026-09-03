<?php

declare(strict_types=1);

namespace App\Etl\EventSubscriber;

use App\Etl\Entity\FicheImportJob;
use App\Etl\Enum\ImportJobStatus;
use App\Etl\Message\ProcessFicheImportBatch;
use App\Shared\Messenger\AbstractWorkerFailureListener;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Uid\Ulid;

/**
 * Le handler ne gère que les erreurs métier (RowOutcome) : une exception
 * imprévue part en retries puis en queue failed, et sans cet écouteur le job
 * resterait « EnCours » pour toujours côté UI.
 */
#[AsEventListener]
final readonly class FicheImportFailureSubscriber extends AbstractWorkerFailureListener
{
    protected function concerne(object $message): bool
    {
        return $message instanceof ProcessFicheImportBatch && Ulid::isValid($message->jobId);
    }

    protected function marquer(EntityManagerInterface $manager, object $message, WorkerMessageFailedEvent $event): void
    {
        /** @var ProcessFicheImportBatch $message */
        $job = $manager->find(FicheImportJob::class, Ulid::fromString($message->jobId));
        if (!$job instanceof FicheImportJob || ImportJobStatus::EnCours !== $job->status()) {
            return;
        }
        $job->fail(sprintf(
            'Import interrompu (lot à partir de la ligne %d) : %s',
            $message->fromLine,
            $event->getThrowable()->getMessage(),
        ));
    }
}
