<?php

namespace App\Pim\Twig\Components\Form;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
class SwitchButton
{
    public string $id;

    public string $name;

    public bool $checked = false;

    /** Valeur soumise — « 1 » pour un booléen, la valeur du choix sinon. */
    public string $value = '1';

    public bool $disabled = false;

    public bool $inverted = false;

    public array $inputAttributes = [];
}
