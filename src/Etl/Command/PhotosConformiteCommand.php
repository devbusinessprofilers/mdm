<?php

declare(strict_types=1);

namespace App\Etl\Command;

use App\Etl\Service\MarketplacePhotoPolicy;
use App\Etl\Service\PhotoPublicationGuard;
use App\Pim\Entity\Fiche;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Enum\NatureRessource;
use App\Pim\Enum\StatutFiche;
use App\Pim\Enum\TypeFiche;
use App\Pim\Message\IndexFiche;
use App\Pim\Repository\FicheRepository;
use App\Shared\Outbox\OutboxPublisherInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Uid\Ulid;

/**
 * Rattrapage des obligations photos sur le stock, à exécuter en fin de
 * pipeline d'import legacy (après app:legacy:import-photos) ou après un
 * changement de seuils :
 *
 *  1. activités et services sans photo principale : la première photo devient
 *     PHOTO_PRINCIPALE (les exports legacy n'ont pas de catégorie master pour
 *     ces gammes) — correction technique, sans transition de workflow ;
 *  2. fiches publiées ne satisfaisant pas les obligations (minimum du type et
 *     principale) : retour en cours et dépublication marketplace via
 *     PhotoPublicationGuard.
 *
 * Sans --appliquer, la commande rapporte sans rien écrire.
 */
#[AsCommand(name: 'app:fiches:conformite-photos', description: 'Pose les photos principales manquantes (activités/services) puis rétrograde les fiches publiées sans les photos requises.')]
final class PhotosConformiteCommand extends Command
{
    private const BATCH_SIZE = 100;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly FicheRepository $fiches,
        private readonly PhotoPublicationGuard $guard,
        private readonly MarketplacePhotoPolicy $policy,
        private readonly OutboxPublisherInterface $outbox,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('appliquer', null, InputOption::VALUE_NONE, 'Écrit les corrections (sinon simple rapport).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $apply = (bool) $input->getOption('appliquer');

        $principales = $this->poserPrincipales($io, $apply);
        $retrogradees = $this->retrograderNonConformes($io, $apply);

        $io->section('Photos principales posées (activités/services)');
        $io->table(['Type', 'Fiches'], self::rows($principales));
        $io->section($apply ? 'Fiches rétrogradées en cours' : 'Fiches publiées non conformes (seraient rétrogradées)');
        $io->table(['Type', 'Fiches'], self::rows($retrogradees));
        if (!$apply) {
            $io->note('Dry-run : aucune écriture effectuée. Relancer avec --appliquer.');
        }

        return Command::SUCCESS;
    }

    /**
     * Première photo = principale pour les activités et services qui ont des
     * photos mais aucune PHOTO_PRINCIPALE, quel que soit le statut : la
     * correction sert autant la diffusion que les futures soumissions.
     *
     * @return array<string, int>
     */
    private function poserPrincipales(SymfonyStyle $io, bool $apply): array
    {
        $counts = [];
        $ids = $this->ficheIds(types: [TypeFiche::Activite, TypeFiche::ServiceEvenementiel]);
        foreach (array_chunk($ids, self::BATCH_SIZE) as $chunk) {
            foreach ($chunk as $id) {
                $fiche = $this->fiches->find($id);
                if (!$fiche instanceof Fiche) {
                    continue;
                }
                $photos = $this->photos($fiche);
                if ([] === $photos || $this->hasPrincipale($photos)) {
                    continue;
                }
                $counts[$fiche->type()->value] = ($counts[$fiche->type()->value] ?? 0) + 1;
                if (!$apply) {
                    continue;
                }
                usort($photos, static fn (RessourceLieu $a, RessourceLieu $b): int => $a->position() <=> $b->position());
                $premiere = $photos[0];
                // Correction technique : le @PreUpdate du détail repasserait
                // sinon une fiche publiée en brouillon pendant le flush.
                $fiche->preserveWorkflowDuring(function () use ($premiere): void {
                    $premiere->changeUsage('PHOTO_PRINCIPALE');
                    $this->entityManager->flush();
                });
                // Réindexation et re-synchronisation marketplace (drapeau main).
                $this->outbox->enqueue(new IndexFiche($fiche->idString()));
            }
            if ($apply) {
                $this->entityManager->flush();
            }
            $this->entityManager->clear();
            $io->write(sprintf("\rPrincipales : %d fiches corrigées…", array_sum($counts)));
        }
        $io->newLine();

        return $counts;
    }

    /**
     * Rétrograde en cours les fiches publiées dont les photos ne satisfont
     * pas les obligations, et retire leur snapshot de la marketplace.
     *
     * @return array<string, int>
     */
    private function retrograderNonConformes(SymfonyStyle $io, bool $apply): array
    {
        $counts = [];
        $ids = $this->ficheIds(status: StatutFiche::Publiee);
        foreach (array_chunk($ids, self::BATCH_SIZE) as $chunk) {
            foreach ($chunk as $id) {
                $fiche = $this->fiches->find($id);
                if (!$fiche instanceof Fiche || StatutFiche::Publiee !== $fiche->status()) {
                    continue;
                }
                if (!$apply) {
                    // En dry-run la première passe n'a pas posé les
                    // principales : ne compter que les fiches qui resteraient
                    // non conformes après elle.
                    if (!$this->guard->compliant($fiche) && !$this->seraitConformeApresPrincipale($fiche)) {
                        $counts[$fiche->type()->value] = ($counts[$fiche->type()->value] ?? 0) + 1;
                    }
                    continue;
                }
                if ($this->guard->enforce($fiche)) {
                    $counts[$fiche->type()->value] = ($counts[$fiche->type()->value] ?? 0) + 1;
                    // Réindexation du nouveau statut ; le scheduler purgera les
                    // photos du snapshot retiré.
                    $this->outbox->enqueue(new IndexFiche($fiche->idString()));
                }
            }
            if ($apply) {
                $this->entityManager->flush();
            }
            $this->entityManager->clear();
            $io->write(sprintf("\rConformité : %d fiches non conformes…", array_sum($counts)));
        }
        $io->newLine();

        return $counts;
    }

    /**
     * @param list<TypeFiche>|null $types
     *
     * @return list<Ulid>
     */
    private function ficheIds(?array $types = null, ?StatutFiche $status = null): array
    {
        $builder = $this->fiches->createQueryBuilder('f')->select('f.id')->orderBy('f.id', 'ASC');
        if (null !== $types) {
            $builder->andWhere('f.type IN (:types)')->setParameter('types', $types);
        }
        if (null !== $status) {
            $builder->andWhere('f.status = :status')->setParameter('status', $status);
        }

        /** @var list<array{id: Ulid}> $rows */
        $rows = $builder->getQuery()->getArrayResult();

        return array_map(static fn (array $row): Ulid => $row['id'], $rows);
    }

    /** La première passe (principale posée) suffirait-elle à rendre la fiche conforme ? */
    private function seraitConformeApresPrincipale(Fiche $fiche): bool
    {
        if (!in_array($fiche->type(), [TypeFiche::Activite, TypeFiche::ServiceEvenementiel], true)) {
            return false;
        }
        $photos = $this->photos($fiche);

        return !$this->hasPrincipale($photos) && count($photos) >= $this->policy->minimumFor($fiche->type());
    }

    /** @return list<RessourceLieu> */
    private function photos(Fiche $fiche): array
    {
        return array_values(array_filter(
            $fiche->resources()->toArray(),
            static fn (RessourceLieu $resource): bool => NatureRessource::Photo === $resource->nature(),
        ));
    }

    /** @param list<RessourceLieu> $photos */
    private function hasPrincipale(array $photos): bool
    {
        foreach ($photos as $photo) {
            if ('PHOTO_PRINCIPALE' === $photo->usage()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, int> $counts
     *
     * @return list<array{0: string, 1: int}>
     */
    private static function rows(array $counts): array
    {
        if ([] === $counts) {
            return [['—', 0]];
        }
        ksort($counts);
        $rows = [];
        foreach ($counts as $type => $count) {
            $rows[] = [$type, $count];
        }

        return $rows;
    }
}
