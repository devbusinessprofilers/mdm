<?php

declare(strict_types=1);

namespace App\GoLive;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Synchronisation initiale MDM → marketplace en deux phases, avec pre-flight.
 * Entre les deux phases, la purge du legacy s'exécute CÔTÉ MARKETPLACE
 * (`app:pim:purge-referentiels-legacy`, autre application) — cette commande
 * la rappelle mais ne peut pas la lancer.
 *
 * Outillage jetable (src/GoLive/) : délègue l'enfilement à la commande
 * pérenne `app:marketplace:sync`.
 */
#[AsCommand(name: 'app:marketplace:go-live', description: 'Synchronisation initiale MDM → marketplace en deux phases : dictionnaire (LOV) puis fiches.')]
final class MarketplaceGoLiveCommand extends Command
{
    public function __construct(
        private readonly Connection $connection,
        private readonly MarketplaceProbeInterface $probe,
        private readonly SousCommandeRunnerFactoryInterface $runnerFactory,
        #[Autowire('%env(MARKETPLACE_SYNC_API_URL)%')] private readonly string $marketplaceUrl,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('phase', null, InputOption::VALUE_REQUIRED, 'Phase à jouer : dictionnaire ou fiches.')
            ->addOption('executer', null, InputOption::VALUE_NONE, 'Enfile réellement la synchronisation (défaut : rapport seul).')
            ->addOption('batch', null, InputOption::VALUE_REQUIRED, 'Taille des lots de la reprise des fiches.', '100');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $executer = (bool) $input->getOption('executer');
        $phase = trim((string) $input->getOption('phase'));

        $io->title('Go-live marketplace — pre-flight');
        $authentification = $this->preflight($io);

        if (!$executer) {
            $io->section('Déroulé');
            $io->listing([
                'Phase 1 : `app:marketplace:go-live --phase=dictionnaire --executer` — pousse le dictionnaire LOV.',
                'Entre les deux : contrôler les adoptions puis purger le legacy CÔTÉ MARKETPLACE (`app:pim:purge-referentiels-legacy`, simulation par défaut, `--force` pour exécuter).',
                'Phase 2 : `app:marketplace:go-live --phase=fiches --executer` — reprise complète des fiches publiées.',
            ]);
            $io->note('Rapport seul : rien n\'a été enfilé. Ajouter --executer avec --phase pour agir.');

            return Command::SUCCESS;
        }

        if (!in_array($phase, ['dictionnaire', 'fiches'], true)) {
            $io->error('Avec --executer, préciser la phase : --phase=dictionnaire ou --phase=fiches.');

            return Command::INVALID;
        }

        if ('' === trim($this->marketplaceUrl)) {
            $io->error('MARKETPLACE_SYNC_API_URL n\'est pas configurée : rien ne peut partir.');

            return Command::FAILURE;
        }
        if (EtapeStatut::Fait !== $authentification->statut) {
            $io->error('Authentification marketplace impossible : '.$authentification->detail);

            return Command::FAILURE;
        }

        $application = $this->getApplication();
        if (null === $application) {
            $io->error('Application console indisponible.');

            return Command::FAILURE;
        }
        $runner = $this->runnerFactory->creer($application, $output);

        if ('dictionnaire' === $phase) {
            $io->section('Phase 1 — dictionnaire LOV');
            if (0 !== $runner->run('app:marketplace:sync', ['--lov' => true])) {
                $io->error('L\'enfilement du dictionnaire LOV a échoué.');

                return Command::FAILURE;
            }
            $io->caution(implode("\n", [
                'Avant la phase fiches : contrôler les adoptions par libellé, puis purger le legacy CÔTÉ MARKETPLACE :',
                '  php bin/console app:pim:purge-referentiels-legacy        (simulation)',
                '  php bin/console app:pim:purge-referentiels-legacy --force',
                'Purger avant la synchronisation du dictionnaire détruirait les relations produit.',
            ]));
            $io->note('Ensuite : `app:marketplace:go-live --phase=fiches --executer`.');

            return Command::SUCCESS;
        }

        $io->section('Phase 2 — reprise des fiches publiées');
        $io->note('Prérequis supposés acquis : phase dictionnaire jouée et legacy purgé côté marketplace.');
        if (0 !== $runner->run('app:marketplace:sync', ['--all' => true, '--batch' => (string) $input->getOption('batch')])) {
            $io->error('L\'enfilement de la reprise a échoué.');

            return Command::FAILURE;
        }
        $io->success('Reprise enfilée — l\'envoi est asynchrone (worker batch, transport marketplace).');
        $io->listing([
            'Suivre l\'avancement : journal `/outils`, famille Marketplace.',
            'Surveiller la file failed : `php bin/console messenger:failed:show` (alerte au-delà du seuil).',
            'Rejouer les erreurs : `php bin/console app:marketplace:sync --failed`.',
        ]);

        return Command::SUCCESS;
    }

    private function preflight(SymfonyStyle $io): EtapeEtat
    {
        $urlConfiguree = '' !== trim($this->marketplaceUrl);
        $authentification = $urlConfiguree ? $this->verifierProbe() : new EtapeEtat(EtapeStatut::NonConfiguree, 'MARKETPLACE_SYNC_API_URL vide');

        $io->table(['Contrôle', 'État', 'Détail'], [
            ['MARKETPLACE_SYNC_API_URL', $urlConfiguree ? EtapeStatut::Fait->value : EtapeStatut::NonConfiguree->value, $urlConfiguree ? '' : 'poser aussi _LOGIN et _PASSWORD (compte machine ROLE_PIM)'],
            ['Authentification (login_check)', $authentification->statut->value, $authentification->detail],
            ['Site marketplace_bp', ...$this->controleSite()],
        ]);
        $io->definitionList(
            ['Fiches publiées' => $this->compter("SELECT COUNT(*) FROM pim_fiche WHERE status = 'publiee'")],
            ['Synchronisations en erreur' => $this->compter("SELECT COUNT(*) FROM etl_fiche_marketplace WHERE status = 'failed'")],
        );

        return $authentification;
    }

    private function verifierProbe(): EtapeEtat
    {
        try {
            return $this->probe->verifier();
        } catch (\Throwable $e) {
            return new EtapeEtat(EtapeStatut::Bloquee, $e->getMessage());
        }
    }

    /** @return array{0: string, 1: string} */
    private function controleSite(): array
    {
        try {
            $present = $this->compter("SELECT COUNT(*) FROM pim_site_diffusion WHERE code = 'marketplace_bp'") > 0;
        } catch (\Throwable $e) {
            return [EtapeStatut::Bloquee->value, $e->getMessage()];
        }

        return $present
            ? [EtapeStatut::Fait->value, '']
            : [EtapeStatut::AFaire->value, 'lancer `app:sites-diffusion:sync` (ou `app:mdm:setup --executer`)'];
    }

    private function compter(string $sql): int
    {
        try {
            return (int) $this->connection->fetchOne($sql);
        } catch (\Throwable) {
            return 0;
        }
    }
}
