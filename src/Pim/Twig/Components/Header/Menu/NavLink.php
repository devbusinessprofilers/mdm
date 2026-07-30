<?php

namespace App\Pim\Twig\Components\Header\Menu;

use App\Pim\Enum\ProviderPortal\Twig\Component\Typography\TypographyTextColorEnum;
use App\Pim\Enum\ProviderPortal\Twig\Component\Typography\TypographyVariantEnum;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class NavLink
{
    public const MAX_DEPTH = 2;

    public string $label;

    public string $route;

    /**
     * @var array<string, string|int>
     */
    public array $routeParameters = [];

    public bool $isActive = false;

    public int $level;

    public TypographyVariantEnum $labelVariant;

    public bool $isLabelBold;

    public bool $isParentExpanded;

    public ?int $notification;

    public ?string $icon = null;

    public ?TypographyTextColorEnum $iconColor = null;

    public function mount(
        string $route,
        bool $isParentExpanded = false,
        int $level = 0,
        ?string $icon = null,
        TypographyTextColorEnum|string|null $iconColor = null,
    ): void {
        $this->route = $route;
        $this->isParentExpanded = $isParentExpanded;
        $this->level = min($level, self::MAX_DEPTH);
        $this->icon = $icon;

        $this->initLabel();
        $this->initIconColor($iconColor);
    }

    private function initIconColor(TypographyTextColorEnum|string|null $iconColor): void
    {
        if ($iconColor instanceof TypographyTextColorEnum) {
            $this->iconColor = $iconColor;

            return;
        }

        $this->iconColor = TypographyTextColorEnum::tryFrom($iconColor) ?? TypographyTextColorEnum::PRIMARY;
    }

    private function initLabel(): void
    {
        switch ($this->level) {
            case 1:
                $this->labelVariant = $this->isParentExpanded ? TypographyVariantEnum::BODY_SMALL : TypographyVariantEnum::BODY_MEDIUM;
                $this->isLabelBold = true;
                break;
            case 2:
                $this->labelVariant = $this->isParentExpanded ? TypographyVariantEnum::BODY_EXTRA_SMALL : TypographyVariantEnum::BODY_SMALL;
                $this->isLabelBold = false;
                break;
            default:
                $this->labelVariant = TypographyVariantEnum::BODY_MEDIUM;
                $this->isLabelBold = true;
        }
    }
}
