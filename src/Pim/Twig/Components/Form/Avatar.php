<?php

namespace App\Pim\Twig\Components\Form;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
class Avatar
{
    public string $id;

    public string $name;

    /**
     * Used to display user initials if no picture or picture not found (i.e. alt attribute on img).
     */
    public ?string $initials = null;

    public ?string $pictureUrl = null;

    public string $accept = '.jpg,.jpeg,.png';

    public bool $disabled = false;

    /**
     * Allows adapting text/background colors of icon depending on background color (blend mode).
     */
    public bool $inverted = false;

    public string $class = '';
}
