<?php

declare(strict_types=1);

namespace App\Etl\Command;

use App\Etl\Service\PhotoPublicationGuard;
use App\Pim\Entity\Fiche;
use App\Pim\Enum\StatutFiche;
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
 * changement de seuils : les fiches publiées ne satisfaisant pas les
 * obligations (minimum du type, plancher à une photo — la principale est la
 * première de l'ordre) repassent en cours et sont dépubliées de la
 * marketplace via PhotoPublicationGuard.
 *
 * Sans --appliquer, la commande rapporte sans rien écrire.
 */
#[AsCommand(name: 'app:fiches:conformite-photos', description: 'Rétrograde les fiches publiées sans les photos requises.')]
final class PhotosConformiteCommand extends Command
{
    private const BATCH_SIZE = 100;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly FicheRepository $fiches,
        private readonly PhotoPublicationGuard $guard,
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

        $retrogradees = $this->retrograderNonConformes($io, $apply);

        $io->section($apply ? 'Fiches rétrogradées en cours' : 'Fiches publiées non conformes (seraient rétrogradées)');
        $io->table(['Type', 'Fiches'], self::rows($retrogradees));
        if (!$apply) {
            $io->note('Dry-run : aucune écriture effectuée. Relancer avec --appliquer.');
        }

        return Command::SUCCESS;
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
                    if (!$this->guard->compliant($fiche)) {
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

    /** @return list<Ulid> */
    private function ficheIds(StatutFiche $status): array
    {
        $builder = $this->fiches->createQueryBuilder('f')->select('f.id')->orderBy('f.id', 'ASC');
        $builder->andWhere('f.status = :status')->setParameter('status', $status);

        /** @var list<array{id: Ulid}> $rows */
        $rows = $builder->getQuery()->getArrayResult();

        return array_map(static fn (array $row): Ulid => $row['id'], $rows);
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
