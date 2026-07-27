<?php

declare(strict_types=1);

namespace App\Shared\Command;

use App\Shared\Outbox\OutboxRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:outbox:purge', description: 'Purge les événements publiés et les anciens reçus.')]
final class OutboxPurgeCommand extends Command
{
    public function __construct(private readonly OutboxRepository $repository)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('published-days', null, InputOption::VALUE_REQUIRED, 'Rétention des événements publiés.', '30')
            ->addOption('processed-days', null, InputOption::VALUE_REQUIRED, 'Rétention des reçus de déduplication.', '180');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $publishedDays = max(1, (int) $input->getOption('published-days'));
        $processedDays = max(1, (int) $input->getOption('processed-days'));
        $now = new \DateTimeImmutable();
        $result = $this->repository->purge(
            $now->modify(sprintf('-%d days', $publishedDays)),
            $now->modify(sprintf('-%d days', $processedDays)),
        );

        $io->success(sprintf(
            '%d événement(s) publié(s) et %d reçu(s) supprimé(s).',
            $result['outbox'],
            $result['receipts'],
        ));

        return Command::SUCCESS;
    }
}
