<?php

namespace App\Pim\Twig\Components\Header\Menu;

use App\Pim\Enum\ProviderPortal\Twig\Component\Typography\TypographyTextColorEnum;
use App\Pim\Enum\ProviderPortal\Twig\Component\Typography\TypographyVariantEnum;
use App\Pim\Model\ProviderPortal\DTO\Menu\MenuDTOItem;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class DropdownNavLink
{
    public const MAX_DEPTH = 1;

    /**
     * @var array<MenuDTOItem>
     */
    public array $items = [];

    public string $label;

    public string $currentRoute;

    /** @var array<string, mixed> */
    public array $currentRouteParameters = [];

    public bool $isActive = false;

    public bool $isFloating = false;

    public bool $isFixed = false;

    public bool $isParentExpanded;

    public int $level;

    public TypographyVariantEnum $labelVariant;

    public ?int $notification;

    public ?string $icon = null;

    public ?TypographyTextColorEnum $iconColor = null;

    /**
     * @param array<MenuDTOItem> $items
     */
    public function mount(
        array $items,
        int $level = 0,
        bool $isParentExpanded = false,
        ?string $icon = null,
        TypographyTextColorEnum|string|null $iconColor = null,
    ): void {
        $this->items = $items;
        $this->level = min($level, self::MAX_DEPTH);
        $this->isParentExpanded = $isParentExpanded;
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

        // PHP 8.5 déprécie tryFrom(null) : garde explicite.
        $this->iconColor = (null !== $iconColor ? TypographyTextColorEnum::tryFrom($iconColor) : null) ?? TypographyTextColorEnum::PRIMARY;
    }

    private function initLabel(): void
    {
        switch ($this->level) {
            case 1:
                $this->labelVariant = $this->isParentExpanded ? TypographyVariantEnum::BODY_SMALL : TypographyVariantEnum::BODY_MEDIUM;
                break;
            default:
                $this->labelVariant = TypographyVariantEnum::BODY_MEDIUM;
        }
    }
}
