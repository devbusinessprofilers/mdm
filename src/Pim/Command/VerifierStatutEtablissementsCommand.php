<?php

declare(strict_types=1);

namespace App\Pim\Command;

use App\Pim\Enum\SuggestionAction;
use App\Pim\Enum\SuggestionSource;
use App\Pim\Repository\FicheEnrichmentScanRepository;
use App\Pim\Repository\LieuRepository;
use App\Pim\Service\EnrichissementIndisponibleException;
use App\Pim\Service\FicheSuggestionEnregistreur;
use App\Pim\Service\StatutEtablissementVerifier;
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
 * Vérifie l'état d'activité des lieux (France) contre l'annuaire des entreprises
 * (Sirene) et en tire des suggestions à arbitrer :
 *
 *  - par défaut, RAPPORT seul — aucune écriture, un CSV dans var/tmp ;
 *  - --appliquer : crée/rafraîchit les suggestions « Suggestions en attente »
 *    (établissement cessé → archivage proposé ; SIRET/TVA manquant → backfill) ;
 *  - --inclure-sans-siret : ajoute le rapprochement par nom + code postal pour
 *    les lieux sans SIRET stocké (plus lent, plus d'appels API, backfill SIRET) ;
 *    par défaut on se limite aux lieux déjà porteurs d'un SIRET (contrôle exact).
 *
 * Gardé par le paramètre sirene.verif_statut_actif (défaut off) : le run
 * planifié est un no-op tant qu'il n'est pas activé dans /admin/parametres ;
 * --forcer permet un test manuel. Gros volume : APP_DEBUG=0.
 */
#[AsCommand(name: 'app:pim:verifier-statut-etablissements', description: 'Détecte les lieux cessés (Sirene) et propose archivage / backfill SIRET-TVA en suggestions.')]
final class VerifierStatutEtablissementsCommand extends Command
{
    /** Échecs API consécutifs avant abandon du run (quota épuisé : inutile d'insister). */
    private const MAX_ERREURS_CONSECUTIVES = 5;

    public function __construct(
        private readonly LieuRepository $lieux,
        private readonly StatutEtablissementVerifier $verifier,
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
            ->addOption('inclure-sans-siret', null, InputOption::VALUE_NONE, 'Rapproche aussi les lieux sans SIRET par nom + code postal (backfill).')
            ->addOption('rescan', null, InputOption::VALUE_NONE, 'Re-scanne tous les lieux (ignore les scans récents), sinon seulement les jamais scannés / modifiés depuis / périmés.')
            ->addOption('forcer', null, InputOption::VALUE_NONE, 'Ignore le paramètre sirene.verif_statut_actif.')
            ->addOption('code', null, InputOption::VALUE_REQUIRED, 'Code d\'une fiche précise.')
            ->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Taille des lots de lieux (max 1000).', '500');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if (!$this->parametres->bool('sirene.verif_statut_actif') && !$input->getOption('forcer')) {
            $io->warning('Vérification Sirene désactivée (sirene.verif_statut_actif). Rien à faire — --forcer pour un test manuel.');

            return Command::SUCCESS;
        }
        $appliquer = (bool) $input->getOption('appliquer');
        $inclureSansSiret = (bool) $input->getOption('inclure-sans-siret');
        $code = null === $input->getOption('code') ? null : (int) $input->getOption('code');
        $batch = max(1, min(1000, (int) $input->getOption('batch-size')));
        // Incrémental : ne (re)scanne que les jamais scannés, modifiés depuis le
        // scan, ou scannés avant le seuil de fraîcheur ; --rescan force tout.
        $now = new \DateTimeImmutable();
        $sourceFiltre = $input->getOption('rescan') ? null : SuggestionSource::Sirene->value;
        $seuil = $now->modify(sprintf('-%d days', max(0, $this->parametres->int('sirene.rescan_apres_jours'))));

        $stats = ['lieux analysés' => 0, 'cessés' => 0, 'backfill SIRET' => 0, 'backfill TVA' => 0, 'suggestions créées' => 0, 'erreurs API' => 0];
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
                if (!$inclureSansSiret && null === $lieu->administratif()->infoLegaleSiret()) {
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
                // Le lieu est effectivement confronté à Sirene : il compte comme scanné.
                $scannesLot[] = $lieu->id();
                ++$stats['lieux analysés'];
                if ([] === $propositions) {
                    continue;
                }
                foreach ($propositions as $proposition) {
                    if (SuggestionAction::Archiver === $proposition->action) {
                        ++$stats['cessés'];
                    } elseif ('info_legale_siret' === $proposition->champ) {
                        ++$stats['backfill SIRET'];
                    } elseif ('info_legale_num_tva' === $proposition->champ) {
                        ++$stats['backfill TVA'];
                    }
                    $rapport[] = [
                        'code' => (string) $lieu->fiche()->code(),
                        'nom' => (string) $lieu->fiche()->label(),
                        'action' => $proposition->action->value,
                        'champ' => $proposition->champ,
                        'proposition' => (string) $proposition->valeurProposee,
                        'score' => null === $proposition->score ? '' : number_format($proposition->score, 2),
                    ];
                }
                if ($appliquer) {
                    $stats['suggestions créées'] += $this->enregistreur->enregistrer($lieu->fiche(), SuggestionSource::Sirene, $propositions);
                }
            }
            if ($appliquer) {
                $this->entityManager->flush();
                // Trace le passage (seulement en application) : le prochain run
                // incrémental sautera ces lieux tant qu'ils ne changent pas.
                $this->scans->marquer($scannesLot, SuggestionSource::Sirene, $now);
            }
            // Vide l'EM à chaque lot (rapport comme application) : sans cela,
            // l'itération sur tout le parc Lieu accumulerait les entités hydratées.
            $this->entityManager->clear();
        } while (!$abandon && count($lieux) === $batch);

        $fichier = $this->exporterRapport($rapport);
        $io->table(array_keys($stats), [array_values($stats)]);
        $io->text(sprintf('Rapport détaillé : %s', $fichier));
        if ($abandon) {
            $io->warning(sprintf('Sirene indisponible (%d échecs consécutifs) : scan interrompu, les lieux restants seront retentés au prochain run.', self::MAX_ERREURS_CONSECUTIVES));

            return Command::FAILURE;
        }
        $io->success($appliquer ? 'Suggestions créées/rafraîchies.' : 'Rapport généré, aucune écriture.');

        return Command::SUCCESS;
    }

    /** @param list<array{code: string, nom: string, action: string, champ: string, proposition: string, score: string}> $rapport */
    private function exporterRapport(array $rapport): string
    {
        $dossier = $this->projectDir.'/var/tmp';
        if (!is_dir($dossier)) {
            mkdir($dossier, 0775, true);
        }
        $fichier = sprintf('%s/verification-statut-etablissements-%s.csv', $dossier, date('Ymd-His'));
        $handle = fopen($fichier, 'w');
        if (false === $handle) {
            throw new \RuntimeException(sprintf('Impossible d\'écrire le rapport %s.', $fichier));
        }
        fputcsv($handle, ['code fiche', 'nom', 'action', 'champ', 'proposition', 'score'], escape: '\\');
        foreach ($rapport as $ligne) {
            fputcsv($handle, array_values($ligne), escape: '\\');
        }
        fclose($handle);

        return $fichier;
    }
}
