<?php

declare(strict_types=1);

namespace App\Pim\Command;

use App\Pim\Enum\SuggestionSource;
use App\Pim\Repository\FicheEnrichmentScanRepository;
use App\Pim\Repository\LieuRepository;
use App\Pim\Service\EnrichissementIndisponibleException;
use App\Pim\Service\FicheSuggestionEnregistreur;
use App\Pim\Service\GeoapifyClient;
use App\Pim\Service\LieuAttributsVerifier;
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
 * Enrichit les lieux (tous pays, dès qu'un GPS est présent) à partir de
 * Geoapify Place Details (tags OpenStreetMap) et en tire des suggestions à
 * arbitrer : classement en étoiles (typologie), enseigne (chaîne / groupe
 * hôtelier), site web, téléphone. On ne propose que des valeurs absentes.
 *
 *  - par défaut, RAPPORT seul — aucune écriture, un CSV dans var/tmp ;
 *  - --appliquer : crée/rafraîchit les suggestions.
 *
 * Gardé par geoapify.enrichissement_places (défaut off) et par la présence de
 * GEOAPIFY_API_KEY. --forcer permet un test manuel. Gros volume : APP_DEBUG=0.
 * ~20 000 lieux pour un quota gratuit de 3 000 requêtes/jour : la commande
 * s'arrête au quota (échecs consécutifs) et REPREND au prochain run là où elle
 * s'était arrêtée (scan-tracking) — la relancer sur plusieurs jours.
 */
#[AsCommand(name: 'app:pim:enrichir-lieux', description: 'Propose typologie (étoiles), chaîne, site et téléphone des lieux depuis Geoapify (OpenStreetMap).')]
final class EnrichirLieuxCommand extends Command
{
    /** Échecs API consécutifs avant abandon du run (quota épuisé : inutile d'insister). */
    private const MAX_ERREURS_CONSECUTIVES = 5;

    public function __construct(
        private readonly LieuRepository $lieux,
        private readonly LieuAttributsVerifier $verifier,
        private readonly FicheSuggestionEnregistreur $enregistreur,
        private readonly FicheEnrichmentScanRepository $scans,
        private readonly GeoapifyClient $geoapify,
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
            ->addOption('forcer', null, InputOption::VALUE_NONE, 'Ignore le paramètre geoapify.enrichissement_places.')
            ->addOption('code', null, InputOption::VALUE_REQUIRED, 'Code d\'une fiche précise.')
            ->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Taille des lots de lieux (max 1000).', '200');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if (!$this->parametres->bool('geoapify.enrichissement_places') && !$input->getOption('forcer')) {
            $io->warning('Enrichissement Geoapify désactivé (geoapify.enrichissement_places). --forcer pour un test manuel.');

            return Command::SUCCESS;
        }
        if (!$this->geoapify->isConfigured()) {
            $io->error('GEOAPIFY_API_KEY absente : impossible d\'interroger Geoapify Places.');

            return Command::FAILURE;
        }
        $appliquer = (bool) $input->getOption('appliquer');
        $code = null === $input->getOption('code') ? null : (int) $input->getOption('code');
        $batch = max(1, min(1000, (int) $input->getOption('batch-size')));
        $now = new \DateTimeImmutable();
        $sourceFiltre = $input->getOption('rescan') ? null : SuggestionSource::Geoapify->value;
        $seuil = $now->modify(sprintf('-%d days', max(0, $this->parametres->int('geoapify.rescan_apres_jours'))));

        $stats = ['lieux analysés' => 0, 'avec propositions' => 0, 'suggestions créées' => 0, 'erreurs API' => 0];
        $rapport = [];
        $after = null;
        $erreursConsecutives = 0;
        $abandon = false;
        do {
            $lieux = $this->lieux->findBatchAfter($after, $batch, $sourceFiltre, $seuil);
            $scannesLot = [];
            foreach ($lieux as $lieu) {
                $after = $lieu->id();
                if (null !== $code && $lieu->fiche()->code() !== $code) {
                    continue;
                }
                try {
                    $propositions = $this->verifier->analyser($lieu);
                } catch (EnrichissementIndisponibleException) {
                    // Panne ou quota : le lieu n'est PAS marqué scanné, il sera
                    // retenté au prochain run au lieu d'être gelé 180 jours.
                    ++$stats['erreurs API'];
                    if (++$erreursConsecutives >= self::MAX_ERREURS_CONSECUTIVES) {
                        $abandon = true;
                        break;
                    }

                    continue;
                }
                $erreursConsecutives = 0;
                $scannesLot[] = $lieu->id();
                ++$stats['lieux analysés'];
                if ([] === $propositions) {
                    continue;
                }
                ++$stats['avec propositions'];
                foreach ($propositions as $proposition) {
                    $rapport[] = [
                        'code' => (string) $lieu->fiche()->code(),
                        'nom' => (string) $lieu->fiche()->label(),
                        'champ' => $proposition->champ,
                        'proposition' => (string) $proposition->valeurProposee,
                    ];
                }
                if ($appliquer) {
                    $stats['suggestions créées'] += $this->enregistreur->enregistrer($lieu->fiche(), SuggestionSource::Geoapify, $propositions);
                }
            }
            if ($appliquer) {
                $this->entityManager->flush();
                $this->scans->marquer($scannesLot, SuggestionSource::Geoapify, $now);
            }
            $this->entityManager->clear();
        } while (!$abandon && count($lieux) === $batch);

        $fichier = $this->exporterRapport($rapport);
        $io->table(array_keys($stats), [array_values($stats)]);
        $io->text(sprintf('Rapport détaillé : %s', $fichier));
        $io->text('Données © OpenStreetMap contributors, via Geoapify.');
        if ($abandon) {
            $io->warning(sprintf('Geoapify indisponible (%d échecs consécutifs) : scan interrompu, les lieux restants seront retentés au prochain run.', self::MAX_ERREURS_CONSECUTIVES));

            return Command::FAILURE;
        }
        $io->success($appliquer ? 'Suggestions créées/rafraîchies.' : 'Rapport généré, aucune écriture.');

        return Command::SUCCESS;
    }

    /** @param list<array{code: string, nom: string, champ: string, proposition: string}> $rapport */
    private function exporterRapport(array $rapport): string
    {
        $dossier = $this->projectDir.'/var/tmp';
        if (!is_dir($dossier)) {
            mkdir($dossier, 0775, true);
        }
        $fichier = sprintf('%s/enrichissement-lieux-%s.csv', $dossier, date('Ymd-His'));
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
