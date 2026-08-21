<?php

declare(strict_types=1);

namespace App\Pim\Command;

use App\Pim\Enum\TypeFiche;
use App\Pim\Message\AnalyzeFicheTexts;
use App\Pim\Repository\FicheRepository;
use App\Shared\Outbox\OutboxPublisherInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:pim:analyze-texts', description: 'Planifie la détection de doublons de textes sur toutes les fiches porteuses de texte libre.')]
final class AnalyzeFicheTextsCommand extends Command
{
    /** Les traiteurs n'ont pas de champ de texte libre enrôlé. */
    private const TYPES = [TypeFiche::Lieu, TypeFiche::Restaurant, TypeFiche::Activite, TypeFiche::ServiceEvenementiel];

    public function __construct(
        private readonly FicheRepository $fiches,
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
        $scheduled = 0;
        foreach (self::TYPES as $type) {
            $cursor = null;
            do {
                $fiches = $this->fiches->findBatchAfter($type, $cursor, $batchSize);
                foreach ($fiches as $fiche) {
                    $this->outbox->enqueue(new AnalyzeFicheTexts($fiche->idString()));
                    $cursor = $fiche->idString();
                    ++$scheduled;
                }
                $this->entityManager->flush();
                $this->entityManager->clear();
            } while (count($fiches) === $batchSize);
        }

        $output->writeln(sprintf('%d fiche(s) planifiée(s) pour détection de doublons de textes.', $scheduled));

        return Command::SUCCESS;
    }
}
