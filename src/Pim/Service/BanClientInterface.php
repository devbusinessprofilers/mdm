<?php

declare(strict_types=1);

namespace App\Pim\Service;

interface BanClientInterface
{
    public function isConfigured(): bool;

    /**
     * Vérifie un lot d'adresses françaises contre la Base Adresse Nationale.
     *
     * @param list<array{id: string, adresse: string, codePostal: string, ville: string}> $lignes
     *
     * @return array<array-key, array{score: ?float, label: ?string, name: ?string, codePostal: ?string, ville: ?string, latitude: ?string, longitude: ?string, type: ?string}> indexé par id (PHP force les clés numériques en int)
     *
     * @throws \RuntimeException quand la BAN est injoignable ou répond hors 2xx
     */
    public function verifierLot(array $lignes): array;
}
