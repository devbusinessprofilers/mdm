<?php

declare(strict_types=1);

namespace App\Pim\Command;

use App\Etl\Service\MarketplaceSyncScheduler;
use App\Pim\Repository\FicheRepository;
use App\Pim\Service\ReferentielGeographiqueFrancais;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Reprise des localisations existantes sur le référentiel géographique
 * français : libellés canoniques INSEE des régions et départements, codes
 * postaux français réparés de leur zéro initial. Les nouvelles écritures
 * sont normalisées par l'entité Localisation elle-même — cette commande ne
 * sert qu'à rattraper le stock. Aucune transition de workflow ; les fiches
 * modifiées repartent vers la marketplace par la sync habituelle.
 *
 * Gros volume : exécuter avec APP_DEBUG=0.
 */
#[AsCommand(name: 'app:localisation:normaliser', description: 'Normalise régions, départements et codes postaux des localisations sur le référentiel français.')]
final class NormaliserLocalisationsCommand extends Command
{
    public function __construct(
        private readonly FicheRepository $fiches,
        private readonly MarketplaceSyncScheduler $marketplaceScheduler,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Analyse sans rien écrire en base.')
            ->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Taille des lots.', '200');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $batch = max(1, min(1000, (int) $input->getOption('batch-size')));
        $stats = ['fiches' => 0, 'régions' => 0, 'départements' => 0, 'codes postaux' => 0, 'fiches modifiées' => 0];
        /** @var array<string, int> $incoherences code fiche => occurrences */
        $incoherences = [];
        $after = null;
        do {
            $fiches = $this->fiches->findWithLocalisationAfter($after, $batch);
            foreach ($fiches as $fiche) {
                $after = $fiche->idString();
                ++$stats['fiches'];
                $localisation = $fiche->localisation();
                if (null === $localisation) {
                    continue;
                }
                $modifie = false;
                $region = $localisation->region();
                if (null !== $region && ReferentielGeographiqueFrancais::normaliserRegion($region) !== $region) {
                    ++$stats['régions'];
                    $modifie = true;
                    if (!$dryRun) {
                        $localisation->changeRegion($region);
                    }
                }
                $departement = $localisation->departement();
                if (null !== $departement && ReferentielGeographiqueFrancais::normaliserDepartement($departement) !== $departement) {
                    ++$stats['départements'];
                    $modifie = true;
                    if (!$dryRun) {
                        $localisation->changeDepartement($departement);
                    }
                }
                $codePostal = $localisation->codePostal();
                if (null !== $codePostal) {
                    // La réparation d'entité est gardée par le pays : on ne la
                    // simule ici que pour la France.
                    $estFrance = 'FR' === $localisation->countryCode()
                        || (null !== $localisation->pays() && 'france' === ReferentielGeographiqueFrancais::cle($localisation->pays()));
                    if ($estFrance && ReferentielGeographiqueFrancais::reparerCodePostal($codePostal) !== $codePostal) {
                        ++$stats['codes postaux'];
                        $modifie = true;
                        if (!$dryRun) {
                            $localisation->changeCodePostal($codePostal);
                        }
                        $codePostal = $localisation->codePostal();
                    }
                    if ($estFrance && null !== $departement && null !== $codePostal
                        && false === ReferentielGeographiqueFrancais::codePostalCoherent($codePostal, $departement)) {
                        $incoherences[sprintf('%d (%s ↔ %s)', $fiche->code(), $codePostal, $departement)] = 1;
                    }
                }
                if ($modifie) {
                    ++$stats['fiches modifiées'];
                    if (!$dryRun) {
                        $this->marketplaceScheduler->schedule($fiche);
                    }
                }
            }
            if (!$dryRun) {
                $this->entityManager->flush();
                $this->entityManager->clear();
            }
        } while (count($fiches) === $batch);
        if (!$dryRun) {
            $this->entityManager->flush();
        }

        $io->table(array_keys($stats), [array_values($stats)]);
        if ([] !== $incoherences) {
            $io->warning(sprintf(
                'Code postal incohérent avec le département sur %d fiche(s) — à arbitrer à la main : %s',
                count($incoherences),
                implode(', ', array_slice(array_keys($incoherences), 0, 30)).(count($incoherences) > 30 ? '…' : ''),
            ));
        }
        $io->success($dryRun ? 'Analyse terminée (aucune écriture).' : 'Normalisation terminée.');

        return Command::SUCCESS;
    }
}
