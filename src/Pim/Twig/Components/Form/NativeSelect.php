<?php

namespace App\Pim\Twig\Components\Form;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Select natif stylé (chevron, bordure primary) pour les choix inline hors
 * formulaire Symfony — ex. la catégorie d'une photo sous sa vignette dans la
 * galerie médias. Les attributs libres (data-action, data-url…) sont posés
 * sur le <select> lui-même.
 */
#[AsTwigComponent]
class NativeSelect
{
    public string $name = '';

    /** @var array<string, string> code => libellé */
    public array $choices = [];

    public ?string $selected = null;

    /** Habillage sombre (barre de salle posée sur la photo). */
    public bool $dark = false;

    public string $class = '';
}
