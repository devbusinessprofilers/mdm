<?php

declare(strict_types=1);

namespace App\Tests\GoLive;

use App\GoLive\SousCommandeRunnerInterface;

final class RecordingSousCommandeRunner implements SousCommandeRunnerInterface
{
    /** @var list<array{commande: string, parametres: array<string, mixed>}> */
    public array $appels = [];

    /** @var array<string, int> code de retour par commande (défaut 0) */
    public array $codesRetour = [];

    public function run(string $commande, array $parametres = []): int
    {
        $this->appels[] = ['commande' => $commande, 'parametres' => $parametres];

        return $this->codesRetour[$commande] ?? 0;
    }

    /** @return list<string> */
    public function commandes(): array
    {
        return array_column($this->appels, 'commande');
    }
}
