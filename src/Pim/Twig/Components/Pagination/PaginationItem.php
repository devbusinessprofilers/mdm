<?php

namespace App\Pim\Twig\Components\Pagination;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class PaginationItem
{
    public string $label;
    public bool $disabled = false;
    public bool $isActive = false;
}
