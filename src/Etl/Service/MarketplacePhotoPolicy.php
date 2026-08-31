<?php

declare(strict_types=1);

namespace App\Etl\Service;

use App\Pim\Entity\Fiche;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Pim\Enum\NatureRessource;
use App\Pim\Enum\TypeFiche;
use App\Pim\Service\PhotoObligations;

/**
 * Obligations photos de la diffusion, mêmes seuils que la soumission
 * (PhotoObligations, surchargeable dans /admin/parametres) : au moins le
 * minimum de photos du type, plancher à une photo — la principale étant la
 * première de l'ordre, toute fiche avec une photo en a une. Une fiche qui ne
 * les respecte plus ne doit plus être servie : elle est dépubliée de la
 * marketplace jusqu'à sa republication PIM.
 *
 * Transition legacy : tant que le PIM ne possède aucune photo de la fiche
 * (imports publiés avant la reprise des médias), la marketplace continue de
 * servir son imagerie historique et la politique ne s'applique pas — d'où
 * `appliesTo()`. Côté marketplace, la même distinction se fait sur les
 * compteurs de photos PIM (`removed + remaining`).
 */
final readonly class MarketplacePhotoPolicy
{
    public function __construct(private PhotoObligations $obligations)
    {
    }

    public function minimumFor(TypeFiche $type): int
    {
        return $this->obligations->minimum($type);
    }

    /** Le PIM possède-t-il l'imagerie de la fiche (au moins une photo) ? */
    public function appliesTo(Fiche $fiche): bool
    {
        return [] !== $this->photoResources($fiche);
    }

    /** Les photos courantes de la fiche suffisent-elles à la diffusion ? */
    public function satisfiedByResources(Fiche $fiche): bool
    {
        // Plancher à 1 : le minimum est surchargeable (il pourrait valoir 0)
        // mais la diffusion exige toujours une principale, donc une photo.
        return count($this->photoResources($fiche)) >= max(1, $this->minimumFor($fiche->type()));
    }

    /**
     * La diffusion reste-t-elle permise ? Vrai pour une fiche conforme comme
     * pour une fiche encore sur imagerie legacy (politique inapplicable).
     */
    public function allowsPublication(Fiche $fiche): bool
    {
        return !$this->appliesTo($fiche) || $this->satisfiedByResources($fiche);
    }

    /**
     * L'état marketplace après purge suffit-il encore à la diffusion ?
     * Le drapeau `main` du snapshot n'entre plus en compte : la principale est
     * la première photo de l'ordre, le prochain sync complet la repousse.
     */
    public function satisfiedByRemote(TypeFiche $type, int $remaining): bool
    {
        return $remaining >= max(1, $this->minimumFor($type));
    }

    /** @return list<RessourceLieu> */
    private function photoResources(Fiche $fiche): array
    {
        return array_values(array_filter(
            $fiche->resources()->toArray(),
            static fn (RessourceLieu $resource): bool => NatureRessource::Photo === $resource->nature(),
        ));
    }
}
