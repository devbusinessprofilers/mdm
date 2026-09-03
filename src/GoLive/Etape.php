<?php

declare(strict_types=1);

namespace App\GoLive;

/**
 * Une étape de mise en place : un contrôle « déjà fait ? » à bas coût et,
 * pour les étapes automatisables, l'exécution d'une sous-commande. Une étape
 * sans exécution est manuelle : elle est rapportée, jamais exécutée.
 */
final readonly class Etape
{
    /**
     * @param \Closure(): EtapeEtat                              $verification
     * @param (\Closure(SousCommandeRunnerInterface): bool)|null $execution
     */
    public function __construct(
        public string $id,
        public string $label,
        private \Closure $verification,
        private ?\Closure $execution = null,
        public ?string $instructions = null,
        public bool $toujoursExecuter = false,
    ) {
    }

    public function manuelle(): bool
    {
        return null === $this->execution;
    }

    public function verifier(): EtapeEtat
    {
        return ($this->verification)();
    }

    public function executer(SousCommandeRunnerInterface $runner): bool
    {
        if (null === $this->execution) {
            return false;
        }

        return ($this->execution)($runner);
    }
}
