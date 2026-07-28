<?php

declare(strict_types=1);

namespace App\Shared\Command;

use App\Shared\Outbox\OutboxRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:outbox:failed:retry', description: 'Replace un événement échoué dans la file de l’outbox.')]
final class OutboxFailedRetryCommand extends Command
{
    public function __construct(private readonly OutboxRepository $repository)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('id', InputArgument::REQUIRED, 'ULID de l’événement à rejouer.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $id = (string) $input->getArgument('id');

        if (!$this->repository->retryFailed($id)) {
            $io->error(sprintf('Aucun événement échoué ne correspond à "%s".', $id));

            return Command::FAILURE;
        }

        $io->success(sprintf('L’événement "%s" sera rejoué.', $id));

        return Command::SUCCESS;
    }
}
