<?php

declare(strict_types=1);

namespace App\Pim\Command;

use App\Pim\Completeness\CompletenessFieldCatalog;
use App\Pim\Repository\CompletenessConfigurationRevisionRepository;
use App\Pim\Repository\CompletenessRepository;
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
        private readonly CompletenessConfigurationRevisionRepository $revisions,
        private readonly CompletenessRepository $completeness,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $rows = [];
        $pendingTotal = 0;
        foreach ($this->catalog->supportedTypes() as $type) {
            $revision = $this->revisions->findForType($type)?->revision() ?? 1;
            $status = $this->completeness->status($type, $revision);
            $pending = (int) $status['pending'];
            $pendingTotal += $pending;
            $rows[] = [$type->value, $revision, (int) $status['total'], $pending, $status['last_calculation'] ?? '—', sprintf('%d / %d / %d', (int) $status['minimum_score'], (int) $status['average_score'], (int) $status['maximum_score'])];
        }
        $io->table(['Type', 'Révision', 'Total', 'En attente', 'Dernier calcul', 'Score min/moy/max'], $rows);

        return 0 === $pendingTotal ? Command::SUCCESS : Command::FAILURE;
    }
}
