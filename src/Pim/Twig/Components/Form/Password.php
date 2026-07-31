<?php

namespace App\Pim\Twig\Components\Form;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
class Password
{
    public string $id;

    public string $name;

    public bool $disabled = false;

    /**
     * Allows to display progress bar with constraints rules.
     */
    public bool $withControl = false;
}
