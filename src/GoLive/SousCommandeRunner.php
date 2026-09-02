<?php

declare(strict_types=1);

namespace App\GoLive;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Exécute les sous-commandes dans le même processus, en partageant la sortie
 * de l'orchestrateur (streaming direct, jamais de sortie bufferisée).
 */
final readonly class SousCommandeRunner implements SousCommandeRunnerInterface
{
    public function __construct(
        private Application $application,
        private OutputInterface $output,
    ) {
    }

    public function run(string $commande, array $parametres = []): int
    {
        $input = new ArrayInput(array_merge(['command' => $commande], $parametres));
        $input->setInteractive(false);

        return $this->application->find($commande)->run($input, $this->output);
    }
}
