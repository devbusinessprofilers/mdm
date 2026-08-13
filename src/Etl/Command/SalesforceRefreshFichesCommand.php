<?php

declare(strict_types=1);

namespace App\Etl\Command;

use App\Etl\Service\SalesforceApiException;
use App\Etl\Service\SalesforceClientInterface;
use App\Etl\Service\SalesforceFicheRefresher;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Rafraîchit les données Salesforce des fiches (notes RSE, évaluation
 * client, procédure judiciaire, contrats, statut partenaire). Exécution
 * quotidienne planifiée (Schedule) ; lancement manuel pour une reprise ou
 * une fiche précise. Les fiches modifiées repartent vers la marketplace
 * par la sync habituelle.
 */
#[AsCommand(name: 'app:salesforce:refresh-fiches', description: 'Rafraîchit les données Salesforce des fiches (RSE, évaluation, procédure, partenaire).')]
final class SalesforceRefreshFichesCommand extends Command
{
    public function __construct(
        private readonly SalesforceClientInterface $salesforce,
        private readonly SalesforceFicheRefresher $refresher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('code', null, InputOption::VALUE_REQUIRED, 'Code d\'une fiche précise (Product2.ID__c)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->salesforce->isConfigured()) {
            $output->writeln('<error>La connexion Salesforce n\'est pas configurée (SALESFORCE_*) : rien ne sera rafraîchi.</error>');

            return Command::FAILURE;
        }
        $code = null;
        $option = trim((string) $input->getOption('code'));
        if ('' !== $option) {
            $code = (int) $option;
            if ($code < 1 || (string) $code !== $option) {
                $output->writeln('<error>Le code doit être un entier strictement positif.</error>');

                return Command::INVALID;
            }
        }
        try {
            $stats = $this->refresher->refresh($code);
        } catch (SalesforceApiException $exception) {
            $output->writeln(sprintf('<error>%s</error>', $exception->getMessage()));

            return Command::FAILURE;
        }
        $output->writeln(sprintf(
            '<info>%d produit(s) Salesforce reçus : %d fiche(s) appariée(s), %d modifiée(s), %d sans fiche PIM.</info>',
            $stats['recus'],
            $stats['matches'],
            $stats['modifies'],
            $stats['inconnus'],
        ));

        return Command::SUCCESS;
    }
}
