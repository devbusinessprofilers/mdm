<?php

namespace App\Pim\Twig\Components\Review;

use App\Pim\Model\ProviderPortal\DTO\Review\ReminderDTO;
use App\Pim\Twig\Components\Pagination\PaginatedListTrait;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
class PaginatedReminderList
{
    use DefaultActionTrait;

    /**
     * @use PaginatedListTrait<ReminderDTO>
     */
    use PaginatedListTrait;

    public function __construct(
        // Reminder repo or some SF service here ?
    ) {
    }

    public function mount(int $page = 1, int $itemsPerPage = 10, int $totalItems = 0): void
    {
        $this->initLivePagination($page, $itemsPerPage, $totalItems);
        $this->items = $this->fetch();
    }

    private function fetch(): array
    {
        return $this->mock($this->currentPage);
    }

    /**
     * @return array<ReminderDTO>
     */
    private function mock(int $page): array
    {
        $mockedReminders = [];
        for ($i = 1; $i <= $this->itemsPerPage; ++$i) {
            $id = $i + ($page - 1) * $this->itemsPerPage;
            if ($id > $this->totalItems) {
                continue;
            }

            $mockedReminders[] = ReminderDTO::mock($i);
        }

        return $mockedReminders;
    }
}
