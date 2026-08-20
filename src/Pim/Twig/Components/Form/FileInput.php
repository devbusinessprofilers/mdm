<?php

namespace App\Pim\Twig\Components\Form;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Input fichier nu et masqué (sr-only) pour les dépôts pilotés par un
 * contrôleur Stimulus : le libellé qui l'enveloppe porte tout le rendu,
 * les attributs data-* passent tels quels sur l'input.
 */
#[AsTwigComponent]
class FileInput
{
    public ?string $accept = null;
}
