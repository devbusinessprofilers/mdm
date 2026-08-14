<?php

namespace App\Pim\Twig\Components\Visibility;

use App\Pim\Enum\ProviderPortal\VisibilityActionEnum;
use App\Pim\Model\ProviderPortal\DTO\SheetDTO;
use App\Pim\Model\ProviderPortal\Visibility\VisibilityAction;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class Actions
{
    public SheetDTO $sheet;

    /**
     * @var array<VisibilityAction>
     */
    public array $actions = [];

    public function getActions(): array
    {
        return $this->mock();
    }

    private function mock(): array
    {
        // Can be changed depending on the type of the sheet
        return [
            (new VisibilityAction(VisibilityActionEnum::LINKEDIN_PUBLICATION))
                ->setAction('#action'),
            (new VisibilityAction(VisibilityActionEnum::MARKETING_RESEARCH))
                ->setAction('#action'),
            (new VisibilityAction(VisibilityActionEnum::ONE_TARGETED_EMAIL))
                ->setAction('#action'),
            (new VisibilityAction(VisibilityActionEnum::TWO_TARGETED_EMAIL))
                ->setAction('#action'),
        ];
    }
}
