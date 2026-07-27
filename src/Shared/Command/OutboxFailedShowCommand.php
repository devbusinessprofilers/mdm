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

#[AsCommand(name: 'app:outbox:failed:show', description: 'Affiche les événements en échec dans l’outbox.')]
final class OutboxFailedShowCommand extends Command
{
    public function __construct(private readonly OutboxRepository $repository)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('id', InputArgument::OPTIONAL, 'ULID d’un événement précis.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $id = $input->getArgument('id');
        $messages = $this->repository->findFailed(is_string($id) ? $id : null);

        if ([] === $messages) {
            $io->success('Aucun événement en échec.');

            return Command::SUCCESS;
        }

        $io->table(
            ['ID', 'Type', 'Tentatives', 'Création', 'Erreur'],
            array_map(static fn (array $row): array => [
                $row['id'],
                $row['message_type'],
                $row['attempts'],
                $row['occurred_at'],
                $row['last_error'],
            ], $messages),
        );

        return Command::SUCCESS;
    }
}
