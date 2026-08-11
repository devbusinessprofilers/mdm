<?php

declare(strict_types=1);

namespace App\Etl\Command;

use App\Etl\Repository\FicheMarketplaceSyncRepository;
use App\Etl\Service\MarketplaceClientInterface;
use App\Etl\Service\MarketplaceSyncScheduler;
use App\Pim\Entity\Fiche;
use App\Pim\Repository\FicheRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Uid\Ulid;

/**
 * Reprise de la synchronisation marketplace : pousse tout le stock publié
 * (reprise initiale), une fiche précise, ou rejoue les fiches en erreur.
 * La commande ne fait qu'enfiler les messages ; l'envoi reste asynchrone
 * (worker du transport `marketplace`).
 */
#[AsCommand(name: 'app:marketplace:sync', description: 'Enfile la synchronisation marketplace des fiches publiées.')]
final class MarketplaceSyncCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly FicheRepository $fiches,
        private readonly FicheMarketplaceSyncRepository $tracking,
        private readonly MarketplaceSyncScheduler $scheduler,
        private readonly MarketplaceClientInterface $client,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('all', null, InputOption::VALUE_NONE, 'Toutes les fiches publiées (reprise initiale)')
            ->addOption('fiche', null, InputOption::VALUE_REQUIRED, 'ULID d\'une fiche précise')
            ->addOption('failed', null, InputOption::VALUE_NONE, 'Rejoue les fiches en erreur')
            ->addOption('batch', null, InputOption::VALUE_REQUIRED, 'Taille des lots de la reprise complète', '100');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->client->isConfigured()) {
            $output->writeln('<error>MARKETPLACE_SYNC_API_URL n\'est pas configurée : rien ne sera envoyé.</error>');

            return Command::FAILURE;
        }
        $ficheId = trim((string) $input->getOption('fiche'));
        if (1 !== count(array_filter([$input->getOption('all'), '' !== $ficheId, $input->getOption('failed')]))) {
            $output->writeln('<error>Choisir exactement une option : --all, --fiche=ULID ou --failed.</error>');

            return Command::INVALID;
        }
        if ('' !== $ficheId) {
            if (!Ulid::isValid($ficheId)) {
                $output->writeln('<error>Identifiant de fiche invalide.</error>');

                return Command::INVALID;
            }
            $fiche = $this->fiches->find(Ulid::fromString($ficheId));
            if (!$fiche instanceof Fiche) {
                $output->writeln('<error>Fiche introuvable.</error>');

                return Command::FAILURE;
            }
            $this->scheduler->schedule($fiche);
            $this->entityManager->flush();
            $output->writeln('<info>1 fiche planifiée.</info>');

            return Command::SUCCESS;
        }
        if ($input->getOption('failed')) {
            $count = 0;
            foreach ($this->tracking->findFailed() as $tracked) {
                $fiche = $this->fiches->find($tracked->ficheId());
                if ($fiche instanceof Fiche) {
                    $this->scheduler->schedule($fiche);
                    ++$count;
                }
            }
            $this->entityManager->flush();
            $output->writeln(sprintf('<info>%d fiche(s) en erreur replanifiée(s).</info>', $count));

            return Command::SUCCESS;
        }
        $batch = max(1, min(1000, (int) $input->getOption('batch')));
        $count = 0;
        $after = null;
        do {
            $fiches = $this->fiches->findPublishedAfter($after, $batch);
            foreach ($fiches as $fiche) {
                $this->scheduler->schedule($fiche);
                ++$count;
                $after = $fiche->idString();
            }
            $this->entityManager->flush();
            $this->entityManager->clear();
        } while (count($fiches) === $batch);
        $output->writeln(sprintf('<info>%d fiche(s) publiée(s) planifiée(s).</info>', $count));

        return Command::SUCCESS;
    }
}
