<?php

declare(strict_types=1);

namespace App\Dashboard\Command;

use App\Dashboard\Message\ComputeDashboardStats;
use App\Dashboard\Message\ComputeFieldFillRates;
use App\Dashboard\MessageHandler\ComputeDashboardStatsHandler;
use App\Dashboard\MessageHandler\ComputeFieldFillRatesHandler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Recalcul immédiat et synchrone des snapshots du tableau de bord (stats +
 * champs peu renseignés), sans attendre les passages planifiés (15 min /
 * quotidien 04:30).
 */
#[AsCommand(name: 'app:dashboard:recompute', description: 'Recalcule immédiatement les snapshots du tableau de bord.')]
final class RecomputeDashboardCommand extends Command
{
    public function __construct(
        private readonly ComputeDashboardStatsHandler $statsHandler,
        private readonly ComputeFieldFillRatesHandler $fieldFillHandler,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        ($this->statsHandler)(new ComputeDashboardStats());
        $io->text('Snapshot des statistiques recalculé.');
        ($this->fieldFillHandler)(new ComputeFieldFillRates());
        $io->text('Snapshot des taux de remplissage recalculé.');
        $io->success('Tableau de bord à jour.');

        return Command::SUCCESS;
    }
}
