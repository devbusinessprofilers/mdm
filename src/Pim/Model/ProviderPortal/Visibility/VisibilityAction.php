<?php

namespace App\Pim\Model\ProviderPortal\Visibility;

use App\Pim\Enum\ProviderPortal\VisibilityActionEnum;

class VisibilityAction
{
    public string $action;
    public string $icon;
    public ?string $tip;

    public function __construct(
        public VisibilityActionEnum $type,
    ) {
        $this->icon = match ($type) {
            VisibilityActionEnum::LINKEDIN_PUBLICATION => 'styled-linkedin',
            VisibilityActionEnum::MARKETING_RESEARCH => 'styled-analytic',
            VisibilityActionEnum::ONE_TARGETED_EMAIL, VisibilityActionEnum::TWO_TARGETED_EMAIL => 'styled-mail',
            default => '',
        };

        $this->tip = match ($type) {
            VisibilityActionEnum::ONE_TARGETED_EMAIL, VisibilityActionEnum::TWO_TARGETED_EMAIL => 'visibility.action.tip.targeted_email',
            default => null,
        };
    }

    public function setAction(string $action): static
    {
        $this->action = $action;

        return $this;
    }
}
