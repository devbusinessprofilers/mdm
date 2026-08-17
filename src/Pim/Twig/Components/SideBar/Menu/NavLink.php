<?php

namespace App\Pim\Twig\Components\SideBar\Menu;

use App\Pim\Enum\ProviderPortal\Twig\Component\Button\ButtonSizeEnum;
use App\Pim\Enum\ProviderPortal\Twig\Component\Typography\TypographyTextColorEnum;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class NavLink
{
    public string $label;
    public string $route;
    /** @var array<string, string|int> */
    public array $routeParameters = [];
    public bool $isActive = false;
    public ?string $icon;
    public ?TypographyTextColorEnum $iconColor;
    public ButtonSizeEnum $size;

    public function mount(
        string $route,
        TypographyTextColorEnum|string|null $iconColor = null,
        ButtonSizeEnum|string|null $size = null,
        ?string $currentRoute = null,
        ?string $icon = null,
    ): void {
        if ($route === $currentRoute) {
            $this->isActive = true;
        }

        $this->route = $route;
        $this->icon = $icon;
        $this->initIconColor($iconColor);
        $this->initSize($size);
    }

    private function initIconColor(TypographyTextColorEnum|string|null $iconColor): void
    {
        if (!$this->icon) {
            $this->iconColor = null;

            return;
        }

        if ($iconColor instanceof TypographyTextColorEnum) {
            $this->iconColor = $iconColor;

            return;
        }

        // PHP 8.5 déprécie tryFrom(null) : garde explicite.
        $this->iconColor = (null !== $iconColor ? TypographyTextColorEnum::tryFrom($iconColor) : null) ?? TypographyTextColorEnum::PRIMARY;
    }

    private function initSize(ButtonSizeEnum|string|null $size): void
    {
        if ($size instanceof ButtonSizeEnum) {
            $this->size = $size;

            return;
        }

        $this->size = (null !== $size ? ButtonSizeEnum::tryFrom($size) : null) ?? ButtonSizeEnum::MEDIUM;
    }
}
