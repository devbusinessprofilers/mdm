<?php

declare(strict_types=1);

namespace App\Pim\Command;

use App\Pim\Enum\SuggestionSource;
use App\Pim\Repository\FicheEnrichmentScanRepository;
use App\Pim\Repository\LieuRepository;
use App\Pim\Service\ChaineHoteliereVerifier;
use App\Pim\Service\FicheSuggestionEnregistreur;
use App\Pim\Service\Wikidata\ChaineDictionnaire;
use App\Pim\Service\Wikidata\WikidataChaineClient;
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
 * Détecte l'affiliation des lieux à une chaîne / marque hôtelière à partir de
 * leur nom, via un dictionnaire (référentiel interne des grands groupes, enrichi
 * de Wikidata) et en tire des suggestions à arbitrer. Backfill seulement.
 *
 *  - par défaut, RAPPORT seul ; --appliquer crée/rafraîchit les suggestions.
 *
 * Gardé par wikidata.detection_chaine (défaut off). Wikidata est un complément :
 * le référentiel interne fonctionne même sans accès au SPARQL. Gros volume :
 * APP_DEBUG=0.
 */
#[AsCommand(name: 'app:pim:enrichir-chaines', description: 'Détecte la chaîne/marque hôtelière des lieux (référentiel interne + Wikidata) en suggestions.')]
final class EnrichirChainesCommand extends Command
{
    public function __construct(
        private readonly LieuRepository $lieux,
        private readonly WikidataChaineClient $wikidata,
        private readonly ChaineHoteliereVerifier $verifier,
        private readonly FicheSuggestionEnregistreur $enregistreur,
        private readonly FicheEnrichmentScanRepository $scans,
        private readonly ParametreProviderInterface $parametres,
        private readonly EntityManagerInterface $entityManager,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('appliquer', null, InputOption::VALUE_NONE, 'Crée/rafraîchit les suggestions (défaut : rapport seul).')
            ->addOption('rescan', null, InputOption::VALUE_NONE, 'Re-scanne tous les lieux (ignore les scans récents).')
            ->addOption('forcer', null, InputOption::VALUE_NONE, 'Ignore le paramètre wikidata.detection_chaine.')
            ->addOption('code', null, InputOption::VALUE_REQUIRED, 'Code d\'une fiche précise.')
            ->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Taille des lots de lieux (max 1000).', '500');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if (!$this->parametres->bool('wikidata.detection_chaine') && !$input->getOption('forcer')) {
            $io->warning('Détection de chaîne désactivée (wikidata.detection_chaine). --forcer pour un test manuel.');

            return Command::SUCCESS;
        }
        $appliquer = (bool) $input->getOption('appliquer');
        $code = null === $input->getOption('code') ? null : (int) $input->getOption('code');
        $batch = max(1, min(1000, (int) $input->getOption('batch-size')));
        $now = new \DateTimeImmutable();
        $sourceFiltre = $input->getOption('rescan') ? null : SuggestionSource::Wikidata->value;
        $seuil = $now->modify(sprintf('-%d days', max(0, $this->parametres->int('wikidata.rescan_apres_jours'))));

        $io->text('Construction du dictionnaire de chaînes (référentiel interne + Wikidata)…');
        $chainesWikidata = $this->wikidata->chaines();
        // L'échec WDQS est silencieux (liste vide) : l'afficher, sinon
        // l'opérateur croit avoir la couverture Wikidata en seed-only.
        if ([] === $chainesWikidata) {
            $io->warning('0 chaîne chargée depuis Wikidata (WDQS indisponible ?) : détection sur le seul référentiel interne.');
        } else {
            $io->text(sprintf('%d chaînes chargées depuis Wikidata.', count($chainesWikidata)));
        }
        $dictionnaire = ChaineDictionnaire::depuis($chainesWikidata);

        $stats = ['lieux analysés' => 0, 'chaînes détectées' => 0, 'suggestions créées' => 0];
        $rapport = [];
        $after = null;
        do {
            $lieux = $this->lieux->findBatchAfter($after, $batch, $sourceFiltre, $seuil);
            $scannesLot = [];
            foreach ($lieux as $lieu) {
                $after = $lieu->id();
                if (null !== $code && $lieu->fiche()->code() !== $code) {
                    continue;
                }
                $scannesLot[] = $lieu->id();
                ++$stats['lieux analysés'];
                $propositions = $this->verifier->analyser($lieu, $dictionnaire);
                if ([] === $propositions) {
                    continue;
                }
                ++$stats['chaînes détectées'];
                $rapport[] = [
                    'code' => (string) $lieu->fiche()->code(),
                    'nom' => (string) $lieu->fiche()->label(),
                    'chaine' => (string) $propositions[0]->valeurProposee,
                ];
                if ($appliquer) {
                    $stats['suggestions créées'] += $this->enregistreur->enregistrer($lieu->fiche(), SuggestionSource::Wikidata, $propositions);
                }
            }
            if ($appliquer) {
                $this->entityManager->flush();
                $this->scans->marquer($scannesLot, SuggestionSource::Wikidata, $now);
            }
            $this->entityManager->clear();
        } while (count($lieux) === $batch);

        $fichier = $this->exporterRapport($rapport);
        $io->table(array_keys($stats), [array_values($stats)]);
        $io->text(sprintf('Rapport détaillé : %s', $fichier));
        $io->success($appliquer ? 'Suggestions créées/rafraîchies.' : 'Rapport généré, aucune écriture.');

        return Command::SUCCESS;
    }

    /** @param list<array{code: string, nom: string, chaine: string}> $rapport */
    private function exporterRapport(array $rapport): string
    {
        $dossier = $this->projectDir.'/var/tmp';
        if (!is_dir($dossier)) {
            mkdir($dossier, 0775, true);
        }
        $fichier = sprintf('%s/enrichissement-chaines-%s.csv', $dossier, date('Ymd-His'));
        $handle = fopen($fichier, 'w');
        if (false === $handle) {
            throw new \RuntimeException(sprintf('Impossible d\'écrire le rapport %s.', $fichier));
        }
        fputcsv($handle, ['code fiche', 'nom', 'chaîne détectée'], escape: '\\');
        foreach ($rapport as $ligne) {
            fputcsv($handle, array_values($ligne), escape: '\\');
        }
        fclose($handle);

        return $fichier;
    }
}
