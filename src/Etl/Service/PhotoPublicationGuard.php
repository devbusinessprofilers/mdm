<?php

declare(strict_types=1);

namespace App\Etl\Service;

use App\Pim\Entity\Fiche;
use App\Pim\Enum\StatutFiche;

/**
 * Invariant de publication : une fiche au statut publié doit satisfaire les
 * obligations photos (minimum du type, plancher à une photo). Contrairement à
 * la politique de diffusion, aucune exemption « imagerie legacy » : une fiche
 * sans photo PIM n'est pas conforme. Appliqué à chaque convergence de
 * mutation (IndexFicheHandler) et par la commande de rattrapage
 * app:fiches:conformite-photos.
 *
 * La rétrogradation dépublie aussi la marketplace (MarketplaceRetrait) : le
 * scheduler seul ne suffit pas, une fiche repassée en cours y conserverait
 * sinon son dernier snapshot publié. La purge des photos reste au scheduler
 * (statut intermédiaire → PruneMarketplacePhotos), appelé après cette garde.
 */
final readonly class PhotoPublicationGuard
{
    public function __construct(
        private MarketplacePhotoPolicy $policy,
        private MarketplaceRetrait $retrait,
    ) {
    }

    /** La fiche publiée satisfait-elle les obligations photos (sans exemption) ? */
    public function compliant(Fiche $fiche): bool
    {
        return $this->policy->satisfiedByResources($fiche);
    }

    /**
     * Rétrograde la fiche en cours si elle est publiée sans satisfaire les
     * obligations photos, et retire son snapshot de la marketplace.
     *
     * @return bool vrai si la fiche a été rétrogradée
     */
    public function enforce(Fiche $fiche): bool
    {
        if (StatutFiche::Publiee !== $fiche->status() || $this->compliant($fiche)) {
            return false;
        }
        $fiche->unpublishForInsufficientPhotos();
        $this->retrait->retirer($fiche);

        return true;
    }
}
