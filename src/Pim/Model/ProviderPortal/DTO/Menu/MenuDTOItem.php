<?php

namespace App\Pim\Model\ProviderPortal\DTO\Menu;

use App\Pim\Enum\ProviderPortal\Twig\Component\Typography\TypographyTextColorEnum;

class MenuDTOItem extends MenuDTO
{
    public ?string $icon = null;
    public ?TypographyTextColorEnum $iconColor = null;
    public ?int $notification = null;
    /** @var array<string, string|int> */
    public array $routeParameters = [];

    public function __construct(
        public string $code,
        public string $label,
        public ?string $route = null,
    ) {
    }

    public function setIcon(string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    public function setIconColor(TypographyTextColorEnum $color): static
    {
        $this->iconColor = $color;

        return $this;
    }

    public function setNotification(int $notification): static
    {
        $this->notification = $notification;

        return $this;
    }

    /**
     * @param array<string, string|int> $routeParameters
     */
    public function setRouteParameters(array $routeParameters): static
    {
        $this->routeParameters = $routeParameters;

        return $this;
    }

    /** @param array<string, mixed> $currentRouteParameters */
    public function isActive(?string $currentRoute = null, array $currentRouteParameters = []): bool
    {
        if (empty($this->items) && empty($this->sections)) {
            if (!$currentRoute || $this->route !== $currentRoute) {
                return false;
            }

            unset($currentRouteParameters['_locale']);

            if (empty($currentRouteParameters)) {
                return true;
            }

            if (empty($this->routeParameters)) {
                return false;
            }

            foreach ($currentRouteParameters as $key => $value) {
                if (!isset($this->routeParameters[$key]) || $this->routeParameters[$key] !== $value) {
                    return false;
                }
            }

            return true;
        }

        if (!empty($this->items)) {
            foreach ($this->items as $item) {
                if ($item->isActive($currentRoute, $currentRouteParameters)) {
                    return true;
                }
            }
        }

        if (!empty($this->sections)) {
            foreach ($this->sections as $section) {
                if ($section->isActive($currentRoute, $currentRouteParameters)) {
                    return true;
                }
            }
        }

        return false;
    }
}
