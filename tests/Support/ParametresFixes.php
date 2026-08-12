<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Shared\Service\ParametreProviderInterface;

/** Fournisseur de paramètres applicatifs en dur, sans base ni cache. */
final readonly class ParametresFixes implements ParametreProviderInterface
{
    /** @param array<string, string> $valeurs */
    public function __construct(private array $valeurs)
    {
    }

    public function bool(string $nom): bool
    {
        return filter_var($this->valeur($nom), FILTER_VALIDATE_BOOL);
    }

    public function int(string $nom): int
    {
        return (int) $this->valeur($nom);
    }

    public function string(string $nom): string
    {
        return $this->valeur($nom);
    }

    private function valeur(string $nom): string
    {
        return $this->valeurs[$nom] ?? throw new \InvalidArgumentException(sprintf('Paramètre applicatif inconnu « %s ».', $nom));
    }
}
