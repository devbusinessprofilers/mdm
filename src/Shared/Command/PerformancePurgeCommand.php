<?php

declare(strict_types=1);

namespace App\Shared\Command;

use App\Dashboard\Repository\LogEntryRepository;
use App\Shared\Metrics\PerfSampleRepository;
use App\Shared\Metrics\WorkerHeartbeatRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Rétention des données de /admin/performance : sans cette purge, la série
 * temporelle (~20 k lignes/jour) et les logs persistés croissent sans limite.
 */
#[AsCommand(name: 'app:performance:purge', description: 'Purge les échantillons de monitoring, heartbeats et logs persistés.')]
final class PerformancePurgeCommand extends Command
{
    public function __construct(
        private readonly PerfSampleRepository $samples,
        private readonly WorkerHeartbeatRepository $heartbeats,
        private readonly LogEntryRepository $logs,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('samples-days', null, InputOption::VALUE_REQUIRED, 'Rétention des échantillons perf_sample.', '7')
            ->addOption('heartbeats-hours', null, InputOption::VALUE_REQUIRED, 'Rétention des heartbeats inactifs.', '48')
            ->addOption('logs-days', null, InputOption::VALUE_REQUIRED, 'Rétention des logs persistés.', '15');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->success(sprintf(
            '%d échantillon(s), %d heartbeat(s) et %d ligne(s) de log supprimés.',
            $this->samples->purge(max(1, (int) $input->getOption('samples-days'))),
            $this->heartbeats->purge(max(1, (int) $input->getOption('heartbeats-hours'))),
            $this->logs->purge(max(1, (int) $input->getOption('logs-days'))),
        ));

        return Command::SUCCESS;
    }
}
