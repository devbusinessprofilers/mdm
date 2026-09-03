<?php

namespace App\Pim\Twig\Components\Form;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
class Checkbox
{
    public string $id;

    public string $name;

    /**
     * Valeur soumise quand la case est cochée. « 1 » pour une case booléenne ;
     * la valeur du choix pour une case issue d'un ChoiceType « expanded ».
     */
    public string $value = '1';

    public bool $checked = false;

    public bool $disabled = false;

    /** @var array<string, mixed> */
    public array $inputAttributes = [];
}
