<?php

declare(strict_types=1);

namespace App\Etl\MessageHandler;

use App\Etl\Entity\FicheImportJob;
use App\Etl\Entity\FicheImportJobError;
use App\Etl\Enum\ImportJobStatus;
use App\Etl\Message\ProcessFicheImportBatch;
use App\Etl\Repository\FicheImportJobRepository;
use App\Etl\Service\ImportCsvReader;
use App\Pim\Import\Dto\RowAction;
use App\Pim\Import\FicheImportRowProcessor;
use App\Shared\Outbox\OutboxPublisherInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Ulid;

#[AsMessageHandler]
final readonly class ProcessFicheImportBatchHandler
{
    public const BATCH_SIZE = 50;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private FicheImportJobRepository $jobs,
        private ImportCsvReader $reader,
        private FicheImportRowProcessor $rowProcessor,
        private OutboxPublisherInterface $outbox,
        private string $importDir,
    ) {
    }

    public function __invoke(ProcessFicheImportBatch $message): void
    {
        $job = $this->findJob($message->jobId);
        if (!$job instanceof FicheImportJob || ImportJobStatus::EnCours !== $job->status()) {
            return;
        }
        // Batch déjà commité lors d'une redélivrance Messenger : ne pas retraiter.
        if ($message->fromLine <= $job->lastProcessedLine()) {
            return;
        }

        $path = $this->importDir.'/'.$job->storagePath();

        try {
            $headers = $this->reader->headers($path);
        } catch (\RuntimeException $exception) {
            $job->fail($exception->getMessage());

            return;
        }

        $created = 0;
        $updated = 0;
        $errors = 0;
        $lastLine = $job->lastProcessedLine();
        $nextLine = null;
        $count = 0;

        foreach ($this->reader->rows($path, $headers, $message->fromLine) as $row) {
            if ($count >= self::BATCH_SIZE) {
                $nextLine = $row->lineNumber;
                break;
            }
            ++$count;

            $outcome = $this->rowProcessor->process($job->type(), $row);
            if (RowAction::Failed === $outcome->action) {
                // Le processeur a pu vider l'EntityManager : recharger le job avant d'y rattacher les erreurs.
                $job = $this->findJob($message->jobId) ?? throw new \RuntimeException('Job d’import disparu en cours de traitement.');
                foreach ($outcome->errors as $error) {
                    $this->entityManager->persist(new FicheImportJobError($job, $error->lineNumber, $error->column, $error->message));
                }
                $this->entityManager->flush();
                ++$errors;
            } elseif (RowAction::Created === $outcome->action) {
                ++$created;
            } else {
                ++$updated;
            }

            $lastLine = $row->lineNumber;
        }

        $job->recordBatch($lastLine, $created, $updated, $errors);
        if (null !== $nextLine) {
            $this->outbox->enqueue(new ProcessFicheImportBatch($job->idString(), $nextLine));
        } else {
            $job->finish();
        }
        $this->entityManager->flush();
    }

    private function findJob(string $jobId): ?FicheImportJob
    {
        if (!Ulid::isValid($jobId)) {
            return null;
        }

        return $this->jobs->find(Ulid::fromString($jobId));
    }
}
