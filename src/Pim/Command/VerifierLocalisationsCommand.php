<?php

declare(strict_types=1);

namespace App\Pim\Command;

use App\Etl\Service\MarketplaceSyncScheduler;
use App\Pim\Entity\Fiche;
use App\Pim\Repository\FicheRepository;
use App\Pim\Service\BanClientInterface;
use App\Pim\Service\GeocodeurAdresses;
use App\Pim\Service\GeocodeurEtrangerInterface;
use App\Pim\Service\LocalisationBanVerifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Vérification en masse des adresses contre leur géocodeur — la BAN pour la
 * France, Geoapify pour l'étranger :
 *
 *  - par défaut, RAPPORT seul — quatre paniers (conformes, enrichissables,
 *    corrections proposées, douteuses) exportés en CSV dans var/tmp ;
 *  - --appliquer : écrit uniquement le sûr et non destructif (logique
 *    partagée LocalisationBanVerifier) au-dessus de --seuil, et trace le
 *    passage (score, empreinte, proposition, écart) ;
 *  - --fournisseur=ban|geoapify|tous : borne la vérification à un fournisseur
 *    (utile pour piloter la dépense de crédits Geoapify).
 *
 * La vérification au fil de l'eau (message VerifierAdresseFiche déclenché à
 * chaque changement d'adresse) suit exactement les mêmes règles. Aucune
 * transition de workflow ; les fiches modifiées repartent vers la
 * marketplace par la sync habituelle. Gros volume : APP_DEBUG=0.
 */
#[AsCommand(name: 'app:localisation:verifier', description: 'Vérifie les adresses contre la BAN (France) et Geoapify (étranger), rapport puis application prudente.')]
final class VerifierLocalisationsCommand extends Command
{
    public function __construct(
        private readonly FicheRepository $fiches,
        private readonly BanClientInterface $ban,
        private readonly GeocodeurEtrangerInterface $etranger,
        private readonly GeocodeurAdresses $geocodeurs,
        private readonly MarketplaceSyncScheduler $marketplaceScheduler,
        private readonly EntityManagerInterface $entityManager,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('appliquer', null, InputOption::VALUE_NONE, 'Écrit les enrichissements sûrs (GPS et champs vides, recasage) au-dessus du seuil.')
            ->addOption('seuil', null, InputOption::VALUE_REQUIRED, 'Score minimal pour appliquer.', (string) LocalisationBanVerifier::SEUIL_DEFAUT)
            ->addOption('code', null, InputOption::VALUE_REQUIRED, 'Code d\'une fiche précise.')
            ->addOption('fournisseur', null, InputOption::VALUE_REQUIRED, 'ban (France), geoapify (étranger) ou tous.', 'tous')
            ->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Taille des lots envoyés aux géocodeurs (max 1000).', '1000');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $fournisseur = (string) $input->getOption('fournisseur');
        if (!in_array($fournisseur, ['ban', 'geoapify', 'tous'], true)) {
            $io->error('--fournisseur accepte ban, geoapify ou tous.');

            return Command::FAILURE;
        }
        $avecBan = 'geoapify' !== $fournisseur;
        $avecEtranger = 'ban' !== $fournisseur;
        if ($avecBan && !$this->ban->isConfigured()) {
            $io->error('BAN_API_ENDPOINT n\'est pas configurée : rien ne sera vérifié côté France.');

            return Command::FAILURE;
        }
        if ($avecEtranger && !$this->etranger->isConfigured()) {
            if ('geoapify' === $fournisseur) {
                $io->error('GEOAPIFY_API_KEY n\'est pas configurée : rien ne sera vérifié côté étranger.');

                return Command::FAILURE;
            }
            $io->warning('GEOAPIFY_API_KEY absente : les adresses étrangères sont ignorées.');
            $avecEtranger = false;
        }
        $appliquer = (bool) $input->getOption('appliquer');
        $seuil = max(0.0, min(1.0, (float) $input->getOption('seuil')));
        $code = null === $input->getOption('code') ? null : (int) $input->getOption('code');
        // Aligné sur le plafond de FicheRepository::findWithLocalisationAfter :
        // un lot plus grand casserait la condition de fin de boucle.
        $batch = max(1, min(1000, (int) $input->getOption('batch-size')));

        $stats = ['vérifiées' => 0, 'conformes' => 0, 'enrichissables' => 0, 'corrections proposées' => 0, 'douteuses' => 0, 'fiches modifiées' => 0];
        $rapport = [];
        $after = null;
        do {
            $fiches = $this->fiches->findWithLocalisationAfter($after, $batch);
            $lots = ['ban' => [], 'geoapify' => []];
            $parId = [];
            foreach ($fiches as $fiche) {
                $after = $fiche->idString();
                if (null !== $code && $fiche->code() !== $code) {
                    continue;
                }
                $localisation = $fiche->localisation();
                $ligne = $this->geocodeurs->ligne($fiche);
                if (null === $localisation || null === $ligne) {
                    continue;
                }
                $etrangere = !LocalisationBanVerifier::estFrancaise($localisation);
                if ($etrangere ? !$avecEtranger : !$avecBan) {
                    continue;
                }
                $parId[$ligne['id']] = $fiche;
                $lots[$etrangere ? 'geoapify' : 'ban'][] = $ligne;
            }
            if ([] !== $parId) {
                $resultats = ([] === $lots['ban'] ? [] : $this->ban->verifierLot($lots['ban']))
                    + ([] === $lots['geoapify'] ? [] : $this->etranger->verifierLot($lots['geoapify']));
                foreach ($parId as $id => $fiche) {
                    $this->classer($fiche, $resultats[$id] ?? null, $seuil, $appliquer, $stats, $rapport);
                }
                if ($appliquer) {
                    $this->entityManager->flush();
                    $this->entityManager->clear();
                }
            }
        } while (count($fiches) === $batch);
        if ($appliquer) {
            $this->entityManager->flush();
        }

        $fichier = $this->exporterRapport($rapport);
        $io->table(array_keys($stats), [array_values($stats)]);
        $io->text(sprintf('Rapport détaillé : %s', $fichier));
        $io->success($appliquer ? 'Vérification appliquée (enrichissements sûrs uniquement).' : 'Rapport généré, aucune écriture.');

        return Command::SUCCESS;
    }

    /**
     * @param array{score: ?float, label: ?string, name: ?string, codePostal: ?string, ville: ?string, latitude: ?string, longitude: ?string, type: ?string}|null $resultat
     * @param array<string, int>                                                                                                                                  $stats
     * @param list<array{panier: string, fournisseur: string, code: int, adresse: string, ban: string, score: string}>                                            $rapport
     */
    private function classer(Fiche $fiche, ?array $resultat, float $seuil, bool $appliquer, array &$stats, array &$rapport): void
    {
        ++$stats['vérifiées'];
        $localisation = $fiche->localisation();
        if (null === $localisation) {
            return;
        }
        $panier = LocalisationBanVerifier::panier($localisation, $resultat, $seuil);
        $score = $resultat['score'] ?? null;
        if ('conforme' !== $panier) {
            $rapport[] = [
                'panier' => 'correction' === $panier ? 'correction' : ('enrichissable' === $panier ? 'enrichissable' : 'douteuse'),
                'fournisseur' => LocalisationBanVerifier::estFrancaise($localisation) ? 'ban' : 'geoapify',
                'code' => $fiche->code(),
                'adresse' => trim(sprintf('%s, %s %s', $localisation->ruePostale() ?? '—', $localisation->codePostal() ?? '', $localisation->ville() ?? '')),
                'ban' => $resultat['label'] ?? '',
                'score' => null === $score ? '' : number_format($score, 2),
            ];
        }
        ++$stats[match ($panier) {
            'conforme' => 'conformes',
            'enrichissable' => 'enrichissables',
            'correction' => 'corrections proposées',
            default => 'douteuses',
        }];
        if (!$appliquer) {
            return;
        }
        $modifie = LocalisationBanVerifier::appliquerEnrichissements($localisation, $resultat, $seuil);
        LocalisationBanVerifier::tracer($localisation, $resultat, $seuil);
        if ($modifie) {
            ++$stats['fiches modifiées'];
            $this->marketplaceScheduler->schedule($fiche);
        }
    }

    /** @param list<array{panier: string, fournisseur: string, code: int, adresse: string, ban: string, score: string}> $rapport */
    private function exporterRapport(array $rapport): string
    {
        $dossier = $this->projectDir.'/var/tmp';
        if (!is_dir($dossier)) {
            mkdir($dossier, 0775, true);
        }
        $fichier = sprintf('%s/verification-adresses-%s.csv', $dossier, date('Ymd-His'));
        $handle = fopen($fichier, 'w');
        if (false === $handle) {
            throw new \RuntimeException(sprintf('Impossible d\'écrire le rapport %s.', $fichier));
        }
        fputcsv($handle, ['panier', 'fournisseur', 'code fiche', 'adresse PIM', 'adresse proposée', 'score'], escape: '\\');
        foreach ($rapport as $ligne) {
            fputcsv($handle, array_values($ligne), escape: '\\');
        }
        fclose($handle);

        return $fichier;
    }
}
