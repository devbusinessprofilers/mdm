<?php

declare(strict_types=1);

namespace App\Pim\Command;

use App\Pim\Completeness\CompletenessFieldCatalog;
use App\Pim\Entity\CompletenessConfigurationRevision;
use App\Pim\Enum\TypeFiche;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:completeness:status', description: 'Affiche la progression et la distribution des scores de complétude.')]
final class CompletenessStatusCommand extends Command
{
    public function __construct(
        private readonly CompletenessFieldCatalog $catalog,
        private readonly EntityManagerInterface $entityManager,
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $rows = [];
        $pendingTotal = 0;
        foreach ($this->catalog->supportedTypes() as $type) {
            $revision = $this->entityManager->find(CompletenessConfigurationRevision::class, $type)?->revision() ?? 1;
            $table = match ($type) {
                TypeFiche::Lieu => 'pim_lieu',
                TypeFiche::Activite => 'pim_activite',
                TypeFiche::Restaurant => 'pim_restaurant',
                TypeFiche::ServiceEvenementiel => 'pim_service_evenementiel',
                default => null,
            };
            if (null === $table) {
                continue;
            }
            /** @var array{total: int|string, pending: int|string, last_calculation: string|null, minimum_score: int|string|null, average_score: int|string|null, maximum_score: int|string|null} $status */
            $status = $this->connection->fetchAssociative(sprintf(
                'SELECT COUNT(*) total, SUM(completeness_revision < ?) pending, MAX(completeness_calculated_at) last_calculation, MIN(completeness_global) minimum_score, ROUND(AVG(completeness_global)) average_score, MAX(completeness_global) maximum_score FROM %s',
                $table,
            ), [$revision]) ?: ['total' => 0, 'pending' => 0, 'last_calculation' => null, 'minimum_score' => null, 'average_score' => null, 'maximum_score' => null];
            $pending = (int) $status['pending'];
            $pendingTotal += $pending;
            $rows[] = [$type->value, $revision, (int) $status['total'], $pending, $status['last_calculation'] ?? '—', sprintf('%d / %d / %d', (int) $status['minimum_score'], (int) $status['average_score'], (int) $status['maximum_score'])];
        }
        $io->table(['Type', 'Révision', 'Total', 'En attente', 'Dernier calcul', 'Score min/moy/max'], $rows);

        return 0 === $pendingTotal ? Command::SUCCESS : Command::FAILURE;
    }
}
