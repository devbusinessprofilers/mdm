<?php

namespace App\Pim\Twig\Components\Form;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
class TagSelectOption
{
    public string $value;

    public string $label;

    public ?string $icon = null;

    public bool $selected = false;
}
