<?php

declare(strict_types=1);

namespace App\Pim\Command;

use App\Pim\Completeness\CompletenessConfigurationSynchronizer;
use App\Pim\Completeness\CompletenessFieldCatalog;
use App\Pim\Completeness\CompletenessRecalculationScheduler;
use App\Pim\Enum\TypeFiche;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:completeness:recalculate', description: 'Planifie le recalcul asynchrone de la complétude.')]
final class RecalculateCompletenessCommand extends Command
{
    public function __construct(
        private readonly CompletenessFieldCatalog $catalog,
        private readonly CompletenessConfigurationSynchronizer $synchronizer,
        private readonly CompletenessRecalculationScheduler $scheduler,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('type', null, InputOption::VALUE_REQUIRED, 'Type de fiche ou all', 'all')
            ->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Taille des lots', '250');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $requested = (string) $input->getOption('type');
        $types = 'all' === $requested ? $this->catalog->supportedTypes() : [TypeFiche::from($requested)];
        $batchSize = max(1, min(1000, (int) $input->getOption('batch-size')));
        foreach ($types as $type) {
            if (!$this->synchronizer->isSynchronized($type)) {
                $output->writeln(sprintf('<error>%s : configuration non synchronisée. Exécuter app:completeness:sync-config.</error>', $type->value));

                return Command::FAILURE;
            }
            $revision = $this->scheduler->schedule($type, true, $batchSize);
            $output->writeln(sprintf('%s : révision %d planifiée.', $type->value, $revision));
        }
        $this->entityManager->flush();

        return Command::SUCCESS;
    }
}
