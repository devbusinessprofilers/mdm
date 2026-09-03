<?php

declare(strict_types=1);

namespace App\Dam\Service;

use App\Pim\Entity\Fiche;
use App\Pim\Service\FicheDetailResolver;
use App\Pim\Service\FicheRouteResolver;

/** Lien vers l'éditeur de la fiche à laquelle un média est rattaché ; null si la fiche n'a plus de ligne détail. */
final readonly class DamFicheLinkResolver
{
    public function __construct(
        private FicheDetailResolver $details,
        private FicheRouteResolver $routes,
    ) {
    }

    public function editUrl(Fiche $fiche): ?string
    {
        // L'éditeur MDM est la vue unique d'une fiche.
        return null === $this->details->pour($fiche) ? null : $this->routes->editUrl($fiche->type(), $fiche->idString());
    }
}
