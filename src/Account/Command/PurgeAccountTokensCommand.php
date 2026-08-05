<?php

declare(strict_types=1);

namespace App\Account\Command;

use App\Account\Repository\AccountInvitationRepository;
use App\Account\Repository\PasswordResetRequestRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:account:purge-expired-tokens', description: 'Supprime les invitations et resets expirés depuis plus de 30 jours.')]
final class PurgeAccountTokensCommand extends Command
{
    public function __construct(
        private readonly AccountInvitationRepository $invitations,
        private readonly PasswordResetRequestRepository $resets,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $before = new \DateTimeImmutable('-30 days');
        $invitations = $this->invitations->deleteExpiredBefore($before);
        $resets = $this->resets->deleteExpiredBefore($before);
        $output->writeln(sprintf('%d invitation(s) et %d reset(s) supprimés.', $invitations, $resets));

        return Command::SUCCESS;
    }
}
