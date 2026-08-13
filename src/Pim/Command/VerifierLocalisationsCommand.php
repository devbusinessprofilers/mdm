<?php

declare(strict_types=1);

namespace App\Pim\Command;

use App\Etl\Service\MarketplaceSyncScheduler;
use App\Pim\Entity\Fiche;
use App\Pim\Entity\Localisation;
use App\Pim\Repository\FicheRepository;
use App\Pim\Service\BanClientInterface;
use App\Pim\Service\ReferentielGeographiqueFrancais;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Vérification des adresses françaises contre l'API Adresse (BAN) :
 *
 *  - par défaut, RAPPORT seul — quatre paniers (conformes, enrichissables,
 *    corrections proposées, douteuses) exportés en CSV dans var/tmp ;
 *  - --appliquer : écrit uniquement le sûr et non destructif (GPS
 *    manquants, code postal ou ville vides, recasage d'une ville identique
 *    aux accents près) au-dessus de --seuil, et trace le passage
 *    (ban_score, ban_verifie_le). Une rue ou une ville réellement
 *    différente n'est JAMAIS remplacée : elle reste dans le rapport.
 *
 * Aucune transition de workflow ; les fiches modifiées repartent vers la
 * marketplace par la sync habituelle. Gros volume : APP_DEBUG=0.
 */
#[AsCommand(name: 'app:localisation:verifier', description: 'Vérifie les adresses françaises contre l\'API Adresse (BAN), rapport puis application prudente.')]
final class VerifierLocalisationsCommand extends Command
{
    private const SCORE_DOUTEUX = 0.4;

    public function __construct(
        private readonly FicheRepository $fiches,
        private readonly BanClientInterface $ban,
        private readonly MarketplaceSyncScheduler $marketplaceScheduler,
        private readonly EntityManagerInterface $entityManager,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('appliquer', null, InputOption::VALUE_NONE, 'Écrit les enrichissements sûrs (GPS et champs vides, recasage) au-dessus du seuil.')
            ->addOption('seuil', null, InputOption::VALUE_REQUIRED, 'Score BAN minimal pour appliquer.', '0.85')
            ->addOption('code', null, InputOption::VALUE_REQUIRED, 'Code d\'une fiche précise.')
            ->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Taille des lots envoyés à la BAN (max 1000).', '1000');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if (!$this->ban->isConfigured()) {
            $io->error('BAN_API_ENDPOINT n\'est pas configurée : rien ne sera vérifié.');

            return Command::FAILURE;
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
            $lot = [];
            $parId = [];
            foreach ($fiches as $fiche) {
                $after = $fiche->idString();
                if (null !== $code && $fiche->code() !== $code) {
                    continue;
                }
                $localisation = $fiche->localisation();
                if (null === $localisation || !self::estFrancaise($localisation)) {
                    continue;
                }
                if (null === $localisation->ruePostale() && null === $localisation->ville()) {
                    continue;
                }
                $id = (string) $fiche->code();
                $parId[$id] = $fiche;
                $lot[] = [
                    'id' => $id,
                    'adresse' => trim(sprintf('%s %s', $localisation->ruePostale() ?? '', $localisation->ville() ?? '')),
                    'codePostal' => $localisation->codePostal() ?? '',
                    'ville' => $localisation->ville() ?? '',
                ];
            }
            if ([] !== $lot) {
                $resultats = $this->ban->verifierLot($lot);
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
     * @param array{score: ?float, label: ?string, codePostal: ?string, ville: ?string, latitude: ?string, longitude: ?string, type: ?string}|null $resultat
     * @param array<string, int>                                                                                                                   $stats
     * @param list<array{panier: string, code: int, adresse: string, ban: string, score: string}>                                                  $rapport
     */
    private function classer(Fiche $fiche, ?array $resultat, float $seuil, bool $appliquer, array &$stats, array &$rapport): void
    {
        ++$stats['vérifiées'];
        $localisation = $fiche->localisation();
        if (null === $localisation) {
            return;
        }
        $score = $resultat['score'] ?? null;
        $adresse = trim(sprintf('%s, %s %s', $localisation->ruePostale() ?? '—', $localisation->codePostal() ?? '', $localisation->ville() ?? ''));
        $ligne = static fn (string $panier) => [
            'panier' => $panier,
            'code' => $fiche->code(),
            'adresse' => $adresse,
            'ban' => $resultat['label'] ?? '',
            'score' => null === $score ? '' : number_format($score, 2),
        ];
        if (null === $resultat || null === $score || $score < self::SCORE_DOUTEUX) {
            ++$stats['douteuses'];
            $rapport[] = $ligne('douteuse');
            $this->tracer($fiche, $localisation, $score, $appliquer, false, $stats);

            return;
        }
        $cpConcorde = null === $resultat['codePostal'] || null === $localisation->codePostal()
            || $resultat['codePostal'] === $localisation->codePostal();
        $villeConcorde = null === $resultat['ville'] || null === $localisation->ville()
            || ReferentielGeographiqueFrancais::cle($resultat['ville']) === ReferentielGeographiqueFrancais::cle($localisation->ville());
        if ($score < $seuil || !$cpConcorde || !$villeConcorde) {
            ++$stats[$score >= $seuil ? 'corrections proposées' : 'douteuses'];
            $rapport[] = $ligne($score >= $seuil ? 'correction' : 'douteuse');
            $this->tracer($fiche, $localisation, $score, $appliquer, false, $stats);

            return;
        }
        // Conforme : enrichissements sûrs seulement.
        $modifie = false;
        if ($appliquer) {
            if (null === $localisation->latitude() && null !== $resultat['latitude'] && null !== $resultat['longitude']) {
                try {
                    $localisation->changeLatitude($resultat['latitude']);
                    $localisation->changeLongitude($resultat['longitude']);
                    $modifie = true;
                } catch (\InvalidArgumentException) {
                    // Coordonnée BAN inattendue : on n'écrit rien.
                }
            }
            if (null === $localisation->codePostal() && null !== $resultat['codePostal']) {
                $localisation->changeCodePostal($resultat['codePostal']);
                $modifie = true;
            }
            if (null === $localisation->ville() && null !== $resultat['ville']) {
                $localisation->changeVille($resultat['ville']);
                $modifie = true;
            }
            // Recasage : même ville aux accents/casse près, orthographe BAN.
            if (null !== $localisation->ville() && null !== $resultat['ville']
                && $localisation->ville() !== $resultat['ville']
                && ReferentielGeographiqueFrancais::cle($localisation->ville()) === ReferentielGeographiqueFrancais::cle($resultat['ville'])) {
                $localisation->changeVille($resultat['ville']);
                $modifie = true;
            }
        }
        $seraitEnrichie = null === $localisation->latitude() && null !== $resultat['latitude'];
        if ($modifie || (!$appliquer && $seraitEnrichie)) {
            ++$stats['enrichissables'];
            $rapport[] = $ligne('enrichissable');
        } else {
            ++$stats['conformes'];
        }
        $this->tracer($fiche, $localisation, $score, $appliquer, $modifie, $stats);
    }

    /** @param array<string, int> $stats */
    private function tracer(Fiche $fiche, Localisation $localisation, ?float $score, bool $appliquer, bool $modifie, array &$stats): void
    {
        if (!$appliquer) {
            return;
        }
        $localisation->recordBanVerification($score);
        if ($modifie) {
            ++$stats['fiches modifiées'];
            $this->marketplaceScheduler->schedule($fiche);
        }
    }

    /** @param list<array{panier: string, code: int, adresse: string, ban: string, score: string}> $rapport */
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
        fputcsv($handle, ['panier', 'code fiche', 'adresse PIM', 'adresse BAN', 'score'], escape: '\\');
        foreach ($rapport as $ligne) {
            fputcsv($handle, array_values($ligne), escape: '\\');
        }
        fclose($handle);

        return $fichier;
    }

    private static function estFrancaise(Localisation $localisation): bool
    {
        return 'FR' === $localisation->countryCode()
            || (null !== $localisation->pays() && 'france' === ReferentielGeographiqueFrancais::cle($localisation->pays()));
    }
}
