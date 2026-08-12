<?php

declare(strict_types=1);

namespace App\Account\Command;

use App\Account\Repository\AccountInvitationRepository;
use App\Account\Repository\PasswordResetRequestRepository;
use App\Shared\Service\ParametreProviderInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:account:purge-expired-tokens', description: 'Supprime les invitations et resets expirés au-delà du délai paramétré (compte.purge_jetons_jours).')]
final class PurgeAccountTokensCommand extends Command
{
    public function __construct(
        private readonly AccountInvitationRepository $invitations,
        private readonly PasswordResetRequestRepository $resets,
        private readonly ParametreProviderInterface $parametres,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $before = new \DateTimeImmutable(sprintf('-%d days', $this->parametres->int('compte.purge_jetons_jours')));
        $invitations = $this->invitations->deleteExpiredBefore($before);
        $resets = $this->resets->deleteExpiredBefore($before);
        $output->writeln(sprintf('%d invitation(s) et %d reset(s) supprimés.', $invitations, $resets));

        return Command::SUCCESS;
    }
}
