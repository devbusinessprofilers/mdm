<?php

declare(strict_types=1);

namespace App\Etl\Service;

interface MarketplaceClientInterface
{
    /** L'URL de la marketplace est-elle configurée pour cet environnement ? */
    public function isConfigured(): bool;

    /**
     * Upsert du snapshot complet de la fiche (PUT /api/pim/fiches/{code}).
     *
     * @param array<string, mixed> $payload
     *
     * @return bool vrai si le snapshot est appliqué, faux sur 409 (séquence
     *              dépassée) : la marketplace détient déjà un état plus récent
     *              et l'état local ne doit pas être marqué synchronisé
     *
     * @throws MarketplaceApiException quand la marketplace est injoignable ou
     *                                 refuse la requête (isRetryable() dit si
     *                                 une relance Messenger a un sens)
     */
    public function upsertFiche(int $code, array $payload): bool;

    /**
     * Dépublication (DELETE /api/pim/fiches/{code}). Une réponse 404 est un
     * succès : la fiche n'a jamais atteint la marketplace.
     *
     * @return bool vrai si la dépublication est effective, faux sur 409
     *              (séquence dépassée)
     *
     * @throws MarketplaceApiException
     */
    public function removeFiche(int $code, string $sequence): bool;
}
