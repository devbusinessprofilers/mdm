<?php

namespace App\Pim\Twig\Components\Collaborator;

use App\Pim\Model\ProviderPortal\DTO\Collaborator\CollaboratorDTO;
use App\Pim\Model\ProviderPortal\Mock\Provider\CollaboratorProvider;
use App\Pim\Twig\Components\Pagination\PaginatedListTrait;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
class PaginatedCollaboratorList
{
    use DefaultActionTrait;

    /**
     * @use PaginatedListTrait<CollaboratorDTO>
     */
    use PaginatedListTrait;

    public function mount(int $currentPage = 1, int $itemsPerPage = 10, int $totalItems = 0): void
    {
        $this->initLivePagination($currentPage, $itemsPerPage, $totalItems);
        $this->items = $this->fetch();
    }

    private function fetch(): array
    {
        $offset = ($this->currentPage - 1) * $this->itemsPerPage;

        return array_slice(CollaboratorProvider::findAll(), $offset, $this->itemsPerPage);
    }
}
