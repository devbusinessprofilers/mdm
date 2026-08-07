<?php

declare(strict_types=1);

namespace App\Etl\Command;

use App\Etl\Entity\LegacyFicheMapping;
use App\Etl\Repository\LegacyFicheMappingRepository;
use App\Pim\Entity\FicheSearchDocument;
use App\Pim\Import\Legacy\LegacyActiviteRowMapper;
use App\Pim\Import\Legacy\LegacyCsvReader;
use App\Pim\Import\Legacy\LegacyMappedActivite;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Import manuel des activités (Gamme « Idée ») depuis le CSV d'export
 * production. Idempotent via etl_legacy_fiche ; code fiche = Id syspad.
 * À exécuter avec DATABASE_URL pointant sur la base cible (mdm_reel).
 */
#[AsCommand(name: 'app:legacy:import-activites', description: 'Importe les fiches Activité depuis le CSV d\'export production.')]
final class ImportLegacyActivitesCommand extends Command
{
    private const DEFAULT_FILE = 'var/tmp/import/lists_infos_produits_v2_06-08-2026_02H24.csv';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LegacyFicheMappingRepository $mappings,
        private readonly LegacyCsvReader $reader,
        private readonly LegacyActiviteRowMapper $mapper,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('file', null, InputOption::VALUE_REQUIRED, 'Chemin du CSV production.', $this->projectDir.'/'.self::DEFAULT_FILE)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Mappe sans rien écrire en base.')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Nombre maximum de lignes du périmètre à traiter.')
            ->addOption('from', null, InputOption::VALUE_REQUIRED, 'Numéro d\'enregistrement de données à partir duquel traiter.', '1')
            ->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Taille des lots de flush.', '50')
            ->addOption('only-syspad', null, InputOption::VALUE_REQUIRED, 'Ne traite que cet Id syspad (debug).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $file = (string) $input->getOption('file');
        $dryRun = (bool) $input->getOption('dry-run');
        $limit = null === $input->getOption('limit') ? null : max(1, (int) $input->getOption('limit'));
        $from = max(1, (int) $input->getOption('from'));
        $batchSize = max(1, (int) $input->getOption('batch-size'));
        $onlySyspad = null === $input->getOption('only-syspad') ? null : (int) $input->getOption('only-syspad');

        $headers = $this->reader->headers($file);
        $known = array_fill_keys($this->mappings->allSyspadIds(), true);

        $counters = ['créées' => 0, 'publiées' => 0, 'brouillons' => 0, 'hors périmètre' => 0, 'déjà importées' => 0, 'erreurs' => 0];
        $warningCounts = [];
        $errors = [];
        $pendingInBatch = 0;
        $processed = 0;

        foreach ($this->reader->records($file, $headers) as $record) {
            if ($record->recordNumber < $from) {
                continue;
            }
            if (null !== $record->error) {
                ++$counters['erreurs'];
                $errors[] = ['ligne' => $record->recordNumber, 'syspad' => '?', 'message' => $record->error];
                continue;
            }
            $row = $record->row;
            if (!$this->mapper->supports($row)) {
                ++$counters['hors périmètre'];
                continue;
            }
            if (null !== $onlySyspad && $row->cell('Id syspad') !== (string) $onlySyspad) {
                continue;
            }
            if (null !== $limit && $processed >= $limit) {
                break;
            }
            ++$processed;

            try {
                $mapped = $this->mapper->map($row);
            } catch (\Throwable $exception) {
                ++$counters['erreurs'];
                $errors[] = ['ligne' => $record->recordNumber, 'syspad' => $row->cell('Id syspad'), 'message' => $exception->getMessage()];
                continue;
            }
            foreach ($mapped->warnings as $warning) {
                $warningCounts[$warning] = ($warningCounts[$warning] ?? 0) + 1;
            }
            if (isset($known[$mapped->syspadId])) {
                ++$counters['déjà importées'];
                continue;
            }
            $known[$mapped->syspadId] = true;

            ++$counters['créées'];
            if ($mapped->publish) {
                ++$counters['publiées'];
            } else {
                ++$counters['brouillons'];
            }
            if ($dryRun) {
                continue;
            }

            $this->persist($mapped);
            if (++$pendingInBatch >= $batchSize) {
                $this->entityManager->flush();
                $this->entityManager->clear();
                $pendingInBatch = 0;
                $io->write(sprintf("\r%d activités créées…", $counters['créées']));
            }
        }
        if (!$dryRun && $pendingInBatch > 0) {
            $this->entityManager->flush();
            $this->entityManager->clear();
        }
        $io->newLine();

        $io->table(['Compteur', 'Valeur'], array_map(null, array_keys($counters), array_values($counters)));
        if ([] !== $warningCounts) {
            ksort($warningCounts);
            $io->section('Warnings');
            $io->table(['Code', 'Occurrences'], array_map(null, array_keys($warningCounts), array_values($warningCounts)));
        }
        if ([] !== $errors) {
            $io->section('Erreurs');
            $io->table(
                ['Ligne', 'Syspad', 'Message'],
                array_map(static fn (array $error): array => [$error['ligne'], $error['syspad'], mb_substr($error['message'], 0, 120)], array_slice($errors, 0, 50)),
            );
            if (count($errors) > 50) {
                $io->text(sprintf('… et %d autres erreurs.', count($errors) - 50));
            }
        }
        if ($dryRun) {
            $io->note('Dry-run : aucune écriture effectuée.');
        }

        return [] === $errors ? Command::SUCCESS : Command::FAILURE;
    }

    private function persist(LegacyMappedActivite $mapped): void
    {
        $activite = $mapped->activite;
        if ($mapped->publish) {
            $activite->fiche()->publishForImport();
        }
        $this->entityManager->persist($activite);
        $localisation = $activite->fiche()->localisation();
        $this->entityManager->persist(new FicheSearchDocument(
            $activite->fiche(),
            implode(' ', array_filter([
                (string) $activite->fiche()->code(),
                $activite->fiche()->label(),
                $localisation?->ville(),
                $localisation?->codePostal(),
                $localisation?->region(),
                $localisation?->departement(),
            ], static fn (?string $value): bool => null !== $value && '' !== $value)),
            $activite->fiche()->version(),
        ));
        $this->entityManager->persist(new LegacyFicheMapping(
            $mapped->syspadId,
            $activite->fiche()->id(),
            $activite->fiche()->label(),
            $mapped->gamme,
            $mapped->photosJson,
        ));
    }
}
