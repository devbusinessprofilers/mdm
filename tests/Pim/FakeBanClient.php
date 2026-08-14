<?php

declare(strict_types=1);

namespace App\Tests\Pim;

use App\Pim\Service\BanClientInterface;

/** Doublure de test : sert des résultats BAN prédéfinis, indexés par id. */
final class FakeBanClient implements BanClientInterface
{
    /** @param array<array-key, array{score: ?float, label: ?string, name?: ?string, codePostal: ?string, ville: ?string, latitude: ?string, longitude: ?string, type: ?string}> $resultats */
    public function __construct(
        public array $resultats = [],
        private readonly bool $configured = true,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->configured;
    }

    public function verifierLot(array $lignes): array
    {
        $retour = [];
        foreach ($lignes as $ligne) {
            if (isset($this->resultats[$ligne['id']])) {
                $retour[$ligne['id']] = $this->resultats[$ligne['id']] + ['name' => null];
            }
        }

        return $retour;
    }
}
