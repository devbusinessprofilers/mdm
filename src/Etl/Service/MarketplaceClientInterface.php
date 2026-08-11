<?php

declare(strict_types=1);

namespace App\Etl\Service;

interface MarketplaceClientInterface
{
    /** L'URL de la marketplace est-elle configurée pour cet environnement ? */
    public function isConfigured(): bool;

    /**
     * Upsert du snapshot complet de la fiche (PUT /api/pim/fiches/{code}).
     * Une réponse 409 (séquence dépassée) est considérée comme un succès :
     * la marketplace détient déjà un état plus récent.
     *
     * @param array<string, mixed> $payload
     *
     * @throws MarketplaceApiException quand la marketplace est injoignable ou
     *                                 refuse la requête (relance Messenger)
     */
    public function upsertFiche(int $code, array $payload): void;

    /**
     * Dépublication (DELETE /api/pim/fiches/{code}). Une réponse 404 est un
     * succès : la fiche n'a jamais atteint la marketplace.
     *
     * @throws MarketplaceApiException
     */
    public function removeFiche(int $code, string $sequence): void;
}
