<?php

declare(strict_types=1);

namespace App\Shared\Command;

use App\Shared\Outbox\OutboxRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:outbox:stats', description: 'Affiche le nombre d’événements par statut.')]
final class OutboxStatsCommand extends Command
{
    public function __construct(private readonly OutboxRepository $repository)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $rows = [];

        foreach ($this->repository->countByStatus() as $status => $total) {
            $rows[] = [$status, $total];
        }

        $io->table(['Statut', 'Total'], $rows);

        return Command::SUCCESS;
    }
}
