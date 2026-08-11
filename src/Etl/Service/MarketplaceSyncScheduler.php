<?php

declare(strict_types=1);

namespace App\Etl\Service;

use App\Etl\Enum\MarketplaceSyncStatus;
use App\Etl\Message\RemoveFicheFromMarketplace;
use App\Etl\Message\SyncFicheMarketplace;
use App\Etl\Repository\FicheMarketplaceSyncRepository;
use App\Pim\Entity\Fiche;
use App\Pim\Enum\StatutFiche;
use App\Pim\Repository\SiteDiffusionRepository;
use App\Shared\Outbox\OutboxPublisherInterface;

/**
 * Décide, à chaque changement de fiche, de l'action marketplace à enfiler
 * dans l'outbox (même transaction que la mutation, sans flush) :
 *
 *  - fiche publiée et diffusée sur le site marketplace → envoi (upsert) ;
 *  - fiche archivée, ou publiée sans le site, alors que la marketplace la
 *    connaît → dépublication ;
 *  - autres statuts (en cours, en attente, validée) → rien : la marketplace
 *    conserve le dernier état publié jusqu'à la republication.
 */
final readonly class MarketplaceSyncScheduler
{
    /** Code du référentiel SiteDiffusion représentant la marketplace. */
    public const SITE_CODE = 'marketplace_bp';

    public function __construct(
        private SiteDiffusionRepository $sites,
        private FicheMarketplaceSyncRepository $tracking,
        private OutboxPublisherInterface $outbox,
        private MarketplaceClientInterface $client,
    ) {
    }

    public function schedule(Fiche $fiche): void
    {
        if (!$this->client->isConfigured()) {
            return;
        }
        $diffusable = $this->diffusable($fiche);
        if (StatutFiche::Publiee === $fiche->status() && $diffusable) {
            $this->outbox->enqueue(new SyncFicheMarketplace($fiche->idString()));

            return;
        }
        $tracked = $this->tracking->forFiche($fiche->id());
        if (null === $tracked || MarketplaceSyncStatus::Removed === $tracked->status()) {
            return;
        }
        if (
            StatutFiche::Archivee === $fiche->status()
            || (StatutFiche::Publiee === $fiche->status() && !$diffusable)
        ) {
            $this->outbox->enqueue(new RemoveFicheFromMarketplace($fiche->idString()));
        }
    }

    /** La fiche a-t-elle sélectionné le site marketplace (actif) ? */
    public function diffusable(Fiche $fiche): bool
    {
        $site = $this->sites->findOneByCode(self::SITE_CODE);

        return null !== $site
            && $site->actif()
            && null !== $site->id()
            && in_array($site->id(), $fiche->siteDiffusionIds(), true);
    }
}
