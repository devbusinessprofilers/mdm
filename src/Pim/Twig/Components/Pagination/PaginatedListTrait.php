<?php

namespace App\Pim\Twig\Components\Pagination;

use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\Metadata\UrlMapping;

/** @template T of object */
trait PaginatedListTrait
{
    #[LiveProp(writable: false)]
    public int $itemsPerPage;

    #[LiveProp(writable: false)]
    public int $totalItems;

    #[LiveProp(writable: false)]
    public int $totalPages;

    #[LiveProp(writable: true, url: new UrlMapping(as: 'page'))]
    public int $currentPage;

    /** @var T[] */
    public array $items = [];

    public function initLivePagination(int $currentPage = 1, int $itemsPerPage = 10, int $totalItems = 0): void
    {
        $this->currentPage = $currentPage;
        $this->itemsPerPage = $itemsPerPage;
        $this->totalItems = $totalItems;
        $this->totalPages = ceil($this->totalItems / $this->itemsPerPage);
    }

    #[LiveAction]
    public function changePage(#[LiveArg] int $page): void
    {
        $this->preChangePage();

        $this->currentPage = $page;
        $this->items = $this->fetch();

        $this->postChangePage();
    }

    #[LiveAction]
    public function nextPage(): void
    {
        $this->preChangePage();

        $nextPage = $this->currentPage + 1;
        if ($nextPage > $this->totalPages) {
            return;
        }

        $this->currentPage = $nextPage;
        $this->items = $this->fetch();

        $this->postChangePage();
    }

    #[LiveAction]
    public function previousPage(): void
    {
        $this->preChangePage();

        $nextPage = $this->currentPage - 1;
        if ($nextPage < 1) {
            return;
        }

        $this->currentPage = $nextPage;
        $this->items = $this->fetch();

        $this->postChangePage();
    }

    /** @return T[] */
    abstract private function fetch(): array;

    private function preChangePage(): void
    {
    }

    private function postChangePage(): void
    {
    }
}
