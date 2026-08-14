<?php

namespace App\Pim\Twig\Components\Form;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
class DocumentFile
{
    public string $id;

    public string $name;

    public ?string $fileName = null;

    public string $accept = '';

    public bool $disabled = false;

    public string $class = '';
}
