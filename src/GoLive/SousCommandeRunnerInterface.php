<?php

declare(strict_types=1);

namespace App\GoLive;

interface SousCommandeRunnerInterface
{
    /**
     * Exécute une commande console de l'application et retourne son code de
     * sortie. La sortie de la sous-commande est streamée telle quelle.
     *
     * @param array<string, mixed> $parametres options ArrayInput ('--file' => …, '--appliquer' => true)
     */
    public function run(string $commande, array $parametres = []): int;
}
