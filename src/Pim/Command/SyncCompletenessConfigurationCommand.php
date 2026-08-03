<?php

declare(strict_types=1);

namespace App\Pim\Command;

use App\Pim\Completeness\CompletenessConfigurationSynchronizer;
use App\Pim\Completeness\CompletenessFieldCatalog;
use App\Pim\Enum\TypeFiche;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:completeness:sync-config', description: 'Synchronise explicitement le catalogue et la configuration de complétude.')]
final class SyncCompletenessConfigurationCommand extends Command
{
    public function __construct(private readonly CompletenessFieldCatalog $catalog, private readonly CompletenessConfigurationSynchronizer $synchronizer)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('type', null, InputOption::VALUE_REQUIRED, 'Type de fiche ou all', 'all');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $requested = (string) $input->getOption('type');
        $types = 'all' === $requested ? $this->catalog->supportedTypes() : [TypeFiche::from($requested)];
        foreach ($types as $type) {
            $result = $this->synchronizer->synchronize($type, 'console');
            $output->writeln(sprintf(
                '%s : %d créé(s), %d désactivé(s), révision %d%s.',
                $type->value,
                $result->created,
                $result->deactivated,
                $result->revision,
                $result->recalculationScheduled ? ', recalcul planifié' : '',
            ));
        }

        return Command::SUCCESS;
    }
}
