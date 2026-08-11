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
final class MarketplaceSyncScheduler
{
    /** Code du référentiel SiteDiffusion représentant la marketplace. */
    public const SITE_CODE = 'marketplace_bp';

    /**
     * Mémo du site marketplace (un seul SELECT par processus, au lieu d'un
     * par fiche lors de app:marketplace:sync --all). Scalaires uniquement :
     * l'entité Doctrine serait détachée par les em->clear() des traitements
     * par lots.
     *
     * @var array{id: int, actif: bool}|null
     */
    private ?array $siteMarketplace = null;
    private bool $siteMarketplaceCharge = false;

    public function __construct(
        private readonly SiteDiffusionRepository $sites,
        private readonly FicheMarketplaceSyncRepository $tracking,
        private readonly OutboxPublisherInterface $outbox,
        private readonly MarketplaceClientInterface $client,
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
        $site = $this->siteMarketplace();

        return null !== $site
            && $site['actif']
            && in_array($site['id'], $fiche->siteDiffusionIds(), true);
    }

    /**
     * Charge une seule fois l'id et le drapeau actif du site marketplace,
     * y compris son absence (référentiel non provisionné).
     *
     * @return array{id: int, actif: bool}|null
     */
    private function siteMarketplace(): ?array
    {
        if (!$this->siteMarketplaceCharge) {
            $site = $this->sites->findOneByCode(self::SITE_CODE);
            $this->siteMarketplace = null !== $site && null !== $site->id()
                ? ['id' => $site->id(), 'actif' => $site->actif()]
                : null;
            $this->siteMarketplaceCharge = true;
        }

        return $this->siteMarketplace;
    }
}
