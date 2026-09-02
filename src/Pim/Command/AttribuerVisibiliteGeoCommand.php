<?php

declare(strict_types=1);

namespace App\Pim\Command;

use App\Pim\Entity\SiteDiffusion;
use App\Pim\Message\IndexFiche;
use App\Pim\Repository\FicheRepository;
use App\Pim\Repository\SiteDiffusionRepository;
use App\Pim\Service\SiteDiffusionGeoAttribueur;
use App\Shared\Outbox\OutboxPublisherInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Rattrapage du stock pour la visibilité géographique (CDC §10.1) : rattache
 * les fiches non archivées aux sites de diffusion dont un critère couvre leur
 * adresse. Ajout seul, sans transition de workflow ; chaque fiche modifiée est
 * réindexée (marketplace comprise). Lancer avec APP_DEBUG=0 (le middleware
 * debug Doctrine accumule le log SQL sur ~26k fiches).
 */
#[AsCommand(name: 'app:pim:attribuer-visibilite-geo', description: 'Attribue les sites de diffusion selon leurs critères géographiques (ajout seul).')]
final class AttribuerVisibiliteGeoCommand extends Command
{
    private const EXEMPLES_PAR_SITE = 3;

    public function __construct(
        private readonly FicheRepository $fiches,
        private readonly SiteDiffusionRepository $sites,
        private readonly OutboxPublisherInterface $outbox,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Aperçu des attributions, aucune écriture.')
            ->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Taille des lots', '250');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $batchSize = max(1, min(1000, (int) $input->getOption('batch-size')));

        if ([] === $this->sitesGeo()) {
            $io->warning('Aucun site actif ne porte de critère géographique : rien à attribuer.');

            return Command::SUCCESS;
        }

        $ids = $this->fiches->findIdsNonArchivees();

        $io->text(sprintf('%d fiche(s) non archivée(s) à examiner%s.', count($ids), $dryRun ? ' (dry-run, aucune écriture)' : ''));
        $io->progressStart(count($ids));

        $parSite = [];
        $fichesTouchees = 0;
        $sansLocalisation = 0;
        foreach (array_chunk($ids, $batchSize) as $lot) {
            // Sites rechargés à chaque lot : le clear() de fin de lot les détache.
            $sitesGeo = $this->sitesGeo();
            foreach ($this->fiches->findByIdsAvecSiteSelections($lot) as $fiche) {
                $io->progressAdvance();
                $localisation = $fiche->localisation();
                if (null === $localisation) {
                    ++$sansLocalisation;
                    continue;
                }
                $dejaPresents = array_fill_keys($fiche->siteDiffusionIds(), true);
                $aAjouter = array_values(array_filter(
                    $sitesGeo,
                    static function (SiteDiffusion $site) use ($dejaPresents, $localisation): bool {
                        $siteId = $site->id();

                        return null !== $siteId && !isset($dejaPresents[$siteId])
                            && SiteDiffusionGeoAttribueur::matche($site, $localisation);
                    },
                ));
                if ([] === $aAjouter) {
                    continue;
                }
                ++$fichesTouchees;
                foreach ($aAjouter as $site) {
                    $parSite[$site->code()] ??= ['label' => $site->label(), 'nombre' => 0, 'exemples' => []];
                    ++$parSite[$site->code()]['nombre'];
                    if (count($parSite[$site->code()]['exemples']) < self::EXEMPLES_PAR_SITE) {
                        $parSite[$site->code()]['exemples'][] = sprintf('%s (%s)', (string) $fiche->label(), $localisation->ville() ?? 'ville inconnue');
                    }
                }
                if (!$dryRun) {
                    $fiche->ajouterSitesDiffusion($aAjouter);
                    $this->outbox->enqueue(new IndexFiche($fiche->idString()));
                }
            }
            if (!$dryRun) {
                $this->entityManager->flush();
            }
            $this->entityManager->clear();
        }
        $io->progressFinish();

        ksort($parSite);
        $io->table(
            ['Site', 'Fiches à ajouter', 'Exemples'],
            array_map(static fn (string $code, array $ligne): array => [
                sprintf('%s (%s)', $ligne['label'], $code),
                $ligne['nombre'],
                implode(', ', $ligne['exemples']),
            ], array_keys($parSite), $parSite),
        );
        $io->success(sprintf(
            '%s : %d fiche(s) concernée(s), %d attribution(s)%s.',
            $dryRun ? 'Dry-run' : 'Attribution',
            $fichesTouchees,
            array_sum(array_column($parSite, 'nombre')),
            $sansLocalisation > 0 ? sprintf(', %d fiche(s) sans localisation ignorée(s)', $sansLocalisation) : '',
        ));

        return Command::SUCCESS;
    }

    /** @return list<SiteDiffusion> Sites actifs porteurs d'au moins un critère. */
    private function sitesGeo(): array
    {
        return array_values(array_filter(
            $this->sites->findActifsOrdonnes(),
            static fn (SiteDiffusion $site): bool => [] !== $site->criteresGeo(),
        ));
    }
}
