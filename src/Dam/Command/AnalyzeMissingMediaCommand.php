<?php

declare(strict_types=1);

namespace App\Dam\Command;

use App\Dam\Message\AnalyzeMedia;
use App\Dam\Repository\MediaAssetRepository;
use App\Shared\Outbox\OutboxPublisherInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:dam:analyze-media', description: 'Planifie le calcul pHash des images DAM qui ne sont pas encore analysées.')]
final class AnalyzeMissingMediaCommand extends Command
{
    public function __construct(
        private readonly MediaAssetRepository $assets,
        private readonly OutboxPublisherInterface $outbox,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Nombre de messages persistés par lot', '250');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $batchSize = max(1, min(1000, (int) $input->getOption('batch-size')));
        $cursor = null;
        $scheduled = 0;
        do {
            $ids = $this->assets->findImageIdsMissingPerceptualHash($cursor, $batchSize);
            foreach ($ids as $id) {
                $this->outbox->enqueue(new AnalyzeMedia($id));
                $cursor = $id;
                ++$scheduled;
            }
            $this->entityManager->flush();
            $this->entityManager->clear();
        } while (count($ids) === $batchSize);

        $output->writeln(sprintf('%d image(s) DAM planifiée(s) pour analyse pHash.', $scheduled));

        return Command::SUCCESS;
    }
}
