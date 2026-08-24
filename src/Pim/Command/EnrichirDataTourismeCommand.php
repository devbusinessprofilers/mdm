<?php

declare(strict_types=1);

namespace App\Pim\Command;

use App\Pim\Enum\SuggestionSource;
use App\Pim\Repository\ActiviteRepository;
use App\Pim\Repository\FicheEnrichmentScanRepository;
use App\Pim\Repository\LieuRepository;
use App\Pim\Repository\LocalisationRepository;
use App\Pim\Service\DataTourisme\DataTourismeFluxReader;
use App\Pim\Service\DataTourisme\DataTourismeIndex;
use App\Pim\Service\DataTourismeVerifier;
use App\Pim\Service\FicheSuggestionEnregistreur;
use App\Shared\Service\ParametreProviderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Enrichit les lieux et activités à partir du flux DATAtourisme (open data des
 * offices de tourisme) : description générale manquante et, pour les lieux,
 * équipements bien-être / installations. Le flux JSON-LD doit avoir été
 * synchronisé dans DATATOURISME_FLUX_DIR.
 *
 * L'index est construit une fois, borné aux codes postaux présents dans le PIM
 * (mémoire). Le rapprochement se fait par nom + code postal. On ne propose que
 * des valeurs absentes de la fiche.
 *
 *  - par défaut, RAPPORT seul ; --appliquer crée/rafraîchit les suggestions.
 *
 * Gardé par datatourisme.import_actif (défaut off) et par la présence du flux.
 * --forcer pour un test manuel. Gros volume : APP_DEBUG=0.
 */
#[AsCommand(name: 'app:pim:enrichir-datatourisme', description: 'Propose descriptions et équipements des lieux/activités depuis le flux DATAtourisme (open data).')]
final class EnrichirDataTourismeCommand extends Command
{
    public function __construct(
        private readonly LieuRepository $lieux,
        private readonly ActiviteRepository $activites,
        private readonly DataTourismeFluxReader $flux,
        private readonly DataTourismeVerifier $verifier,
        private readonly FicheSuggestionEnregistreur $enregistreur,
        private readonly FicheEnrichmentScanRepository $scans,
        private readonly ParametreProviderInterface $parametres,
        private readonly EntityManagerInterface $entityManager,
        private readonly LocalisationRepository $localisations,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('appliquer', null, InputOption::VALUE_NONE, 'Crée/rafraîchit les suggestions (défaut : rapport seul).')
            ->addOption('rescan', null, InputOption::VALUE_NONE, 'Re-scanne toutes les fiches (ignore les scans récents).')
            ->addOption('forcer', null, InputOption::VALUE_NONE, 'Ignore le paramètre datatourisme.import_actif.')
            ->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Taille des lots de fiches (max 1000).', '500');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if (!$this->parametres->bool('datatourisme.import_actif') && !$input->getOption('forcer')) {
            $io->warning('Import DATAtourisme désactivé (datatourisme.import_actif). --forcer pour un test manuel.');

            return Command::SUCCESS;
        }
        if (!$this->flux->isConfigured()) {
            $io->error('DATATOURISME_FLUX_DIR n\'est pas un répertoire de flux valide.');

            return Command::FAILURE;
        }
        $appliquer = (bool) $input->getOption('appliquer');
        $batch = max(1, min(1000, (int) $input->getOption('batch-size')));
        $now = new \DateTimeImmutable();
        $sourceFiltre = $input->getOption('rescan') ? null : SuggestionSource::DataTourisme->value;
        $seuil = $now->modify(sprintf('-%d days', max(0, $this->parametres->int('datatourisme.rescan_apres_jours'))));

        $io->text('Construction de l\'index DATAtourisme (bornée aux codes postaux du PIM)…');
        $index = DataTourismeIndex::depuis($this->flux->lire(), $this->localisations->findDistinctPostalCodes());
        if ($index->estVide()) {
            $io->warning('Aucun POI DATAtourisme indexé pour les codes postaux du PIM.');

            return Command::SUCCESS;
        }

        $stats = ['fiches analysées' => 0, 'avec propositions' => 0, 'suggestions créées' => 0];
        $rapport = [];
        $this->parcourirLieux($index, $batch, $appliquer, $sourceFiltre, $seuil, $now, $stats, $rapport);
        $this->parcourirActivites($index, $batch, $appliquer, $sourceFiltre, $seuil, $now, $stats, $rapport);

        $fichier = $this->exporterRapport($rapport);
        $io->table(array_keys($stats), [array_values($stats)]);
        $io->text(sprintf('Rapport détaillé : %s', $fichier));
        $io->text('Source : DATAtourisme (Licence Ouverte 2.0).');
        $io->success($appliquer ? 'Suggestions créées/rafraîchies.' : 'Rapport généré, aucune écriture.');

        return Command::SUCCESS;
    }

    /**
     * @param array<string, int>                                                         $stats
     * @param list<array{code: string, nom: string, champ: string, proposition: string}> $rapport
     */
    private function parcourirLieux(DataTourismeIndex $index, int $batch, bool $appliquer, ?string $sourceFiltre, \DateTimeImmutable $seuil, \DateTimeImmutable $now, array &$stats, array &$rapport): void
    {
        $after = null;
        do {
            $lieux = $this->lieux->findBatchAfter($after, $batch, $sourceFiltre, $seuil);
            $scannesLot = [];
            foreach ($lieux as $lieu) {
                $after = $lieu->id();
                $scannesLot[] = $lieu->id();
                $this->traiter($lieu->fiche(), $this->verifier->analyserLieu($lieu, $index), $appliquer, $stats, $rapport);
            }
            if ($appliquer) {
                $this->entityManager->flush();
                $this->scans->marquer($scannesLot, SuggestionSource::DataTourisme, $now);
            }
            $this->entityManager->clear();
        } while (count($lieux) === $batch);
    }

    /**
     * @param array<string, int>                                                         $stats
     * @param list<array{code: string, nom: string, champ: string, proposition: string}> $rapport
     */
    private function parcourirActivites(DataTourismeIndex $index, int $batch, bool $appliquer, ?string $sourceFiltre, \DateTimeImmutable $seuil, \DateTimeImmutable $now, array &$stats, array &$rapport): void
    {
        $after = null;
        do {
            $activites = $this->activites->findBatchAfter($after, $batch, $sourceFiltre, $seuil);
            $scannesLot = [];
            foreach ($activites as $activite) {
                $after = $activite->id();
                $scannesLot[] = $activite->id();
                $this->traiter($activite->fiche(), $this->verifier->analyserActivite($activite, $index), $appliquer, $stats, $rapport);
            }
            if ($appliquer) {
                $this->entityManager->flush();
                $this->scans->marquer($scannesLot, SuggestionSource::DataTourisme, $now);
            }
            $this->entityManager->clear();
        } while (count($activites) === $batch);
    }

    /**
     * @param list<\App\Pim\Service\SuggestionProposee>                                  $propositions
     * @param array<string, int>                                                         $stats
     * @param list<array{code: string, nom: string, champ: string, proposition: string}> $rapport
     */
    private function traiter(\App\Pim\Entity\Fiche $fiche, array $propositions, bool $appliquer, array &$stats, array &$rapport): void
    {
        ++$stats['fiches analysées'];
        if ([] === $propositions) {
            return;
        }
        ++$stats['avec propositions'];
        foreach ($propositions as $proposition) {
            $rapport[] = [
                'code' => (string) $fiche->code(),
                'nom' => (string) $fiche->label(),
                'champ' => $proposition->champ,
                'proposition' => (string) $proposition->valeurProposee,
            ];
        }
        if ($appliquer) {
            $stats['suggestions créées'] += $this->enregistreur->enregistrer($fiche, SuggestionSource::DataTourisme, $propositions);
        }
    }

    /** @param list<array{code: string, nom: string, champ: string, proposition: string}> $rapport */
    private function exporterRapport(array $rapport): string
    {
        $dossier = $this->projectDir.'/var/tmp';
        if (!is_dir($dossier)) {
            mkdir($dossier, 0775, true);
        }
        $fichier = sprintf('%s/enrichissement-datatourisme-%s.csv', $dossier, date('Ymd-His'));
        $handle = fopen($fichier, 'w');
        if (false === $handle) {
            throw new \RuntimeException(sprintf('Impossible d\'écrire le rapport %s.', $fichier));
        }
        fputcsv($handle, ['code fiche', 'nom', 'champ', 'proposition'], escape: '\\');
        foreach ($rapport as $ligne) {
            fputcsv($handle, array_values($ligne), escape: '\\');
        }
        fclose($handle);

        return $fichier;
    }
}
