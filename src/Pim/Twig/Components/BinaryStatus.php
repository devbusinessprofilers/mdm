<?php

namespace App\Pim\Twig\Components;

use App\Pim\Enum\ProviderPortal\Twig\Component\Typography\TypographyTextColorEnum;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
class BinaryStatus
{
    public bool $status;
    public string $icon;
    public TypographyTextColorEnum $color;

    public function mount(
        bool $status,
    ): void {
        if ($status) {
            $this->icon = 'check-circle';
            $this->color = TypographyTextColorEnum::SUCCESS;
        } else {
            $this->icon = 'cross-circle';
            $this->color = TypographyTextColorEnum::NEUTRAL_400;
        }
    }
}
