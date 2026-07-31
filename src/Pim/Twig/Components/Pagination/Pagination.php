<?php

namespace App\Pim\Twig\Components\Pagination;

use App\Pim\Model\ProviderPortal\Pagination\PaginationScope;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('Pagination', template: 'pim/components/Pagination/Pagination.html.twig')]
final class Pagination
{
    public int $current;
    public int $total;

    public PaginationScope $scope;

    public array $previousAttributes = [];
    public array $targetAttributes = [];
    public array $nextAttributes = [];

    public function mount(int $current, int $total): void
    {
        $this->current = $current;
        $this->total = $total;

        $this->scope = new PaginationScope($total, $current);
    }

    public function getTargetAttributes(?int $page = null): array
    {
        if (null === $page) {
            return [];
        }

        $attributes = [];
        foreach ($this->targetAttributes as $key => $value) {
            $attributes[$key] = preg_replace(':page:', (string) $page, $value);
        }

        return $attributes;
    }
}
