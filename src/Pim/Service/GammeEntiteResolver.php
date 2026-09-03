<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Pim\Entity\Activite\Activite;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Entity\Service\ServiceEvenementiel;

/**
 * Résout l'entité d'une fiche de gamme (hors Lieu) depuis le segment d'URL
 * (restaurants|activites|services) et son identifiant. Simple restriction de
 * FicheDetailResolver pour les contrôleurs dont la route exclut les lieux.
 */
final readonly class GammeEntiteResolver
{
    public function __construct(private FicheDetailResolver $details)
    {
    }

    public function resolve(string $gamme, string $id): Restaurant|Activite|ServiceEvenementiel|null
    {
        $entite = $this->details->parSlugEtId($gamme, $id);

        return $entite instanceof Restaurant || $entite instanceof Activite || $entite instanceof ServiceEvenementiel
            ? $entite
            : null;
    }
}
