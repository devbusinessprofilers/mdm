<?php

namespace App\Pim\Twig\Components\Form;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
class Textarea
{
    public string $id;

    public string $name;

    public bool $disabled = false;
}
