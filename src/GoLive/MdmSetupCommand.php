<?php

declare(strict_types=1);

namespace App\GoLive;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Mise en place d'un environnement MDM neuf (test/preprod/prod) : pre-flight
 * de chaque prérequis et étape, exécution ordonnée et idempotente de ce qui
 * est automatisable, rapport de ce qui reste manuel.
 *
 * Outillage jetable : tout vit dans src/GoLive/ — à supprimer une fois le
 * MDM en place.
 */
#[AsCommand(name: 'app:mdm:setup', description: 'Met en place un environnement MDM neuf : pre-flight, exécution ordonnée et idempotente, rapport du reste manuel.')]
final class MdmSetupCommand extends Command
{
    public function __construct(
        private readonly SetupEtapesProvider $etapes,
        private readonly SousCommandeRunnerFactoryInterface $runnerFactory,
        private readonly EntityManagerInterface $entityManager,
        #[Autowire('%kernel.debug%')] private readonly bool $kernelDebug,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('executer', null, InputOption::VALUE_NONE, 'Exécute les étapes automatisables (défaut : rapport pre-flight seul).')
            ->addOption('avec-import', null, InputOption::VALUE_NONE, 'Inclut la chaîne d\'import legacy (fichiers sources requis dans var/import ou var/tmp/import).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $executer = (bool) $input->getOption('executer');
        $avecImport = (bool) $input->getOption('avec-import');

        $etapes = $this->etapes->socle($avecImport);
        if ($avecImport) {
            $etapes = array_merge($etapes, $this->etapes->importLegacy());
        }

        $io->title('Mise en place du MDM — '.($executer ? 'exécution' : 'pre-flight (rien ne sera exécuté)'));
        $this->afficherPreflight($io, $etapes);

        if (!$executer) {
            $this->afficherResteManuel($io, $etapes);
            $io->note('Relancer avec --executer pour dérouler les étapes automatisables.');

            return Command::SUCCESS;
        }

        if ($this->kernelDebug && $avecImport) {
            $io->warning('APP_DEBUG=1 : le middleware debug Doctrine accumule les requêtes (risque d\'OOM sur les gros imports). Relancer avec APP_DEBUG=0.');
        }

        $application = $this->getApplication();
        if (null === $application) {
            $io->error('Application console indisponible.');

            return Command::FAILURE;
        }
        $runner = $this->runnerFactory->creer($application, $output);

        foreach ($etapes as $etape) {
            $etat = $this->verifierSansException($etape);
            if (EtapeStatut::Fait === $etat->statut && !$etape->toujoursExecuter) {
                $io->writeln(sprintf('<comment>%s</comment> — ignorée (déjà fait%s)', $etape->label, '' !== $etat->detail ? ' : '.$etat->detail : ''));
                continue;
            }
            if (EtapeStatut::NonConfiguree === $etat->statut) {
                continue; // signalement seul, repris dans le rapport final
            }
            if (EtapeStatut::Bloquee === $etat->statut) {
                $io->error(sprintf('Étape « %s » bloquée : %s', $etape->label, $etat->detail));
                $this->afficherInstruction($io, $etape);
                $this->afficherReprise($io, $avecImport);

                return Command::FAILURE;
            }
            if ($etape->manuelle()) {
                continue; // rapportée en fin d'exécution
            }

            $io->section($etape->label);
            if (!$etape->executer($runner)) {
                $io->error(sprintf('Étape « %s » en échec.', $etape->label));
                $this->afficherReprise($io, $avecImport);

                return Command::FAILURE;
            }
            $this->entityManager->clear();
            gc_collect_cycles();
        }

        $io->success('Étapes automatisables terminées.');
        $io->title('État final');
        $this->afficherPreflight($io, $etapes);
        $this->afficherResteManuel($io, $etapes);

        return Command::SUCCESS;
    }

    /** @param list<Etape> $etapes */
    private function afficherPreflight(SymfonyStyle $io, array $etapes): void
    {
        $lignes = [];
        foreach ($etapes as $etape) {
            $etat = $this->verifierSansException($etape);
            $lignes[] = [$etape->label, $this->statutAffiche($etape, $etat)->value, $etat->detail];
        }
        $io->table(['Étape', 'État', 'Détail'], $lignes);
    }

    /** @param list<Etape> $etapes */
    private function afficherResteManuel(SymfonyStyle $io, array $etapes): void
    {
        $restes = [];
        foreach ($etapes as $etape) {
            $etat = $this->verifierSansException($etape);
            if (EtapeStatut::Fait === $etat->statut || null === $etape->instructions) {
                continue;
            }
            if ($etape->manuelle() || EtapeStatut::Bloquee === $etat->statut || EtapeStatut::NonConfiguree === $etat->statut) {
                $restes[] = sprintf('%s : %s', $etape->label, $etape->instructions);
            }
        }
        if ([] !== $restes) {
            $io->section('Reste à faire manuellement');
            $io->listing($restes);
        }
    }

    private function statutAffiche(Etape $etape, EtapeEtat $etat): EtapeStatut
    {
        if (EtapeStatut::AFaire === $etat->statut && $etape->manuelle()) {
            return EtapeStatut::Manuelle;
        }

        return $etat->statut;
    }

    private function verifierSansException(Etape $etape): EtapeEtat
    {
        try {
            return $etape->verifier();
        } catch (\Throwable $e) {
            return new EtapeEtat(EtapeStatut::Bloquee, $e->getMessage());
        }
    }

    private function afficherInstruction(SymfonyStyle $io, Etape $etape): void
    {
        if (null !== $etape->instructions) {
            $io->note($etape->instructions);
        }
    }

    private function afficherReprise(SymfonyStyle $io, bool $avecImport): void
    {
        $io->note(sprintf('Reprise : relancer `app:mdm:setup --executer%s` — les étapes déjà faites seront ignorées.', $avecImport ? ' --avec-import' : ''));
    }
}
