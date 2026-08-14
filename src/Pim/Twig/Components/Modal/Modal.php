<?php

namespace App\Pim\Twig\Components\Modal;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('Modal', template: 'pim/components/Modal/Modal.html.twig')]
class Modal
{
    public ?string $identifier = null;

    public bool $display = false;
}
