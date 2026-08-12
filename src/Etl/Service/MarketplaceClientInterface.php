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
     * Upsert du snapshot complet du dictionnaire LOV
     * (PUT /api/pim/referentiels).
     *
     * @param array<string, mixed> $payload
     *
     * @return bool vrai si le snapshot est appliqué, faux sur 409 (séquence
     *              dépassée) : la marketplace détient déjà un état plus récent
     *
     * @throws MarketplaceApiException
     */
    public function upsertLovDictionary(array $payload): bool;

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

    /**
     * Purge des photos (PUT /api/pim/fiches/{code}/photos) : la marketplace ne
     * conserve que les locations transmises, sans jamais en ajouter.
     *
     * @param list<string> $locations
     *
     * @return array{removed: int, remaining: int, principaleRemaining: bool}|false|null
     *         les compteurs après purge ; faux sur 409 (séquence dépassée) ;
     *         null sur 404 (fiche inconnue de la marketplace : rien à purger)
     *
     * @throws MarketplaceApiException
     */
    public function pruneFichePhotos(int $code, string $sequence, array $locations): array|false|null;
}
