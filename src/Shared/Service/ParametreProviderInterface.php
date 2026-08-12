<?php

declare(strict_types=1);

namespace App\Shared\Service;

/**
 * Lecture typée des paramètres applicatifs. Les consommateurs appellent au
 * moment de l'usage (pas à la construction) pour que les surcharges faites
 * dans /admin s'appliquent sans redémarrage des workers.
 */
interface ParametreProviderInterface
{
    public function bool(string $nom): bool;

    public function int(string $nom): int;

    public function string(string $nom): string;
}
