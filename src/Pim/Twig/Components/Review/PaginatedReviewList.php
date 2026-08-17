<?php

namespace App\Pim\Twig\Components\Review;

use App\Pim\Enum\ProviderPortal\SheetTypeEnum;
use App\Pim\Model\ProviderPortal\DTO\Review\ReviewDTO;
use App\Pim\Twig\Components\Pagination\PaginatedListTrait;
use Symfony\Component\Form\ChoiceList\View\ChoiceView;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
class PaginatedReviewList
{
    use DefaultActionTrait;

    /**
     * @use PaginatedListTrait<ReviewDTO>
     */
    use PaginatedListTrait;

    #[LiveProp(writable: true)]
    public ?string $currentType = null;

    /**
     * @var array<ChoiceView>
     */
    public array $typeChoices = [];

    public function __construct(
        // Review repo or some SF service here ?
    ) {
    }

    public function mount(int $page = 1, int $itemsPerPage = 10, int $totalItems = 0, ?string $currentType = null): void
    {
        $this->initLivePagination($page, $itemsPerPage, $totalItems);
        $this->initChoices();
        $this->items = $this->fetch();
        // PHP 8.5 déprécie tryFrom(null) : garde explicite.
        $this->currentType = null !== $currentType ? SheetTypeEnum::tryFrom($currentType)?->name : null;
    }

    #[LiveAction]
    public function changeSheetType(): void
    {
        $this->initChoices();

        $this->currentPage = 1;
        $this->items = $this->fetch();
    }

    private function initChoices(): void
    {
        foreach (SheetTypeEnum::cases() as $type) {
            $this->typeChoices[] = new ChoiceView($type, $type->name, $type->getTranslationKey());
        }
    }

    private function fetch(): array
    {
        return $this->mock($this->currentPage, $this->currentType);
    }

    private function preChangePage(): void
    {
        $this->initChoices();
    }

    /** @return ReviewDTO[] */
    private function mock(int $page, ?string $type): array
    {
        $mockedReviews = [];
        for ($i = 1; $i <= $this->itemsPerPage; ++$i) {
            $id = $i + ($page - 1) * $this->itemsPerPage;
            if ($id > $this->totalItems) {
                continue;
            }

            $review = ReviewDTO::mock();

            if ($type) {
                $review->placeName .= \sprintf(' - %s', ucfirst($type));
            }

            $mockedReviews[] = $review;
        }

        return $mockedReviews;
    }
}
