<?php

namespace App\Pim\Twig\Components\Form;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
class PictureFile
{
    public string $id;

    public string $name;

    public ?string $pictureUrl = null;

    public string $accept = '.jpg,.jpeg,.png';

    public bool $disabled = false;

    public string $class = '';
}
