<?php

namespace App\Pim\Twig\Components\Form;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
class SwitchButton
{
    public string $id;

    public string $name;

    public bool $checked = false;

    public bool $disabled = false;

    public bool $inverted = false;

    public array $inputAttributes = [];
}
