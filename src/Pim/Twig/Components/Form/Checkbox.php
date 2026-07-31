<?php

namespace App\Pim\Twig\Components\Form;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
class Checkbox
{
    public string $id;

    public string $name;

    public bool $checked = false;

    public bool $disabled = false;

    public array $inputAttributes = [];
}
