<?php

declare(strict_types=1);

namespace App\Etl\MessageHandler;

use App\Etl\Entity\FicheImportJob;
use App\Etl\Enum\ImportJobStatus;
use App\Etl\Message\ProcessFicheImportBatch;
use App\Etl\Message\StartFicheImport;
use App\Etl\Repository\FicheImportJobRepository;
use App\Etl\Service\ImportCsvReader;
use App\Pim\Export\FicheExportXlsxGenerator;
use App\Pim\Import\FicheImportTemplateGenerator;
use App\Pim\Import\SuggestionProche;
use App\Shared\Outbox\OutboxPublisherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Ulid;

#[AsMessageHandler]
final readonly class StartFicheImportHandler
{
    public const MAX_LINES = 5000;

    public function __construct(
        private FicheImportJobRepository $jobs,
        private ImportCsvReader $reader,
        private FicheImportTemplateGenerator $templates,
        private OutboxPublisherInterface $outbox,
        private string $importDir,
    ) {
    }

    public function __invoke(StartFicheImport $message): void
    {
        if (!Ulid::isValid($message->jobId)) {
            return;
        }
        $job = $this->jobs->find(Ulid::fromString($message->jobId));
        if (!$job instanceof FicheImportJob || ImportJobStatus::EnAttente !== $job->status()) {
            return;
        }

        $path = $this->importDir.'/'.$job->storagePath();
        // Le fichier d'export est multi-feuilles : chaque job lit la feuille
        // de sa gamme.
        $sheet = FicheExportXlsxGenerator::nomFeuille($job->type());

        try {
            $headers = $this->reader->headers($path, $sheet);
        } catch (\RuntimeException $exception) {
            $job->fail($exception->getMessage());

            return;
        }

        $expected = $this->templates->headers($job->type());
        $unknown = array_values(array_diff($headers, $expected));
        if ([] !== $unknown) {
            // Liste complète, chaque colonne avec l'en-tête attendu le plus
            // proche : le fichier se corrige en une passe. Message mono-ligne,
            // affiché en tooltip dans l'historique.
            $details = array_map(static function (string $colonne) use ($expected): string {
                $proche = SuggestionProche::trouver($colonne, $expected);

                return null === $proche ? $colonne : sprintf('%s (proche de : %s)', $colonne, $proche);
            }, $unknown);
            $job->fail(sprintf(
                'Colonnes inconnues : %s. Repartez du fichier d’export du référentiel sans renommer ni ajouter de colonnes.',
                implode(', ', $details),
            ));

            return;
        }
        // Deux colonnes de même nom seraient silencieusement fusionnées à la
        // lecture (la dernière gagne) : rejet explicite.
        $duplicates = array_keys(array_filter(array_count_values($headers), static fn (int $count): bool => $count > 1));
        if ([] !== $duplicates) {
            $job->fail(sprintf('Colonnes en double : %s. Chaque colonne ne doit apparaître qu’une fois.', implode(', ', array_slice($duplicates, 0, 10))));

            return;
        }
        // Le fichier peut ne porter que la colonne code et les colonnes à
        // modifier — `label` n'est exigé que par les lignes de création,
        // contrôlées ligne à ligne.
        if (!in_array('code', $headers, true)) {
            $job->fail('La colonne code est obligatoire.');

            return;
        }

        $total = $this->reader->countDataLines($path, $headers, $sheet);
        if (0 === $total) {
            $job->fail('Aucune ligne de données dans le fichier.');

            return;
        }
        if ($total > self::MAX_LINES) {
            $job->fail(sprintf('Fichier trop volumineux : %d lignes pour un maximum de %d.', $total, self::MAX_LINES));

            return;
        }

        $job->start($total);
        $this->outbox->enqueue(new ProcessFicheImportBatch($job->idString(), 2));
    }
}
