<?php

declare(strict_types=1);

namespace App\Pim\Service;

/**
 * Contrat commun des vérificateurs d'adresses (BAN pour la France, Geoapify
 * pour l'étranger) : un lot de lignes en entrée, un résultat par id au shape
 * unique — la logique aval (paniers, enrichissements sûrs, trace, arbitrage)
 * est agnostique du fournisseur.
 */
interface GeocodeurAdresseInterface
{
    public function isConfigured(): bool;

    /**
     * @param list<array{id: string, adresse: string, codePostal: string, ville: string, pays?: string}> $lignes pays = code ISO-2, ignoré par la BAN
     *
     * @return array<array-key, array{score: ?float, label: ?string, name: ?string, codePostal: ?string, ville: ?string, latitude: ?string, longitude: ?string, type: ?string}> indexé par id (PHP force les clés numériques en int)
     *
     * @throws \RuntimeException quand le service est injoignable ou répond hors 2xx
     */
    public function verifierLot(array $lignes): array;
}
