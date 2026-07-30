<?php

namespace App\Pim\Twig\Components\Table;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
class DeleteButton
{
    public string $path;

    public string $csrfToken;

    private ?string $modalIdentifier = null;

    public function getModalIdentifier(): string
    {
        if (null === $this->modalIdentifier) {
            $this->modalIdentifier = uniqid();
        }

        return $this->modalIdentifier;
    }
}
