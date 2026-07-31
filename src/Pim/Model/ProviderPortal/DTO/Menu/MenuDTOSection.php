<?php

namespace App\Pim\Model\ProviderPortal\DTO\Menu;

class MenuDTOSection extends MenuDTO
{
    public function __construct(
        public string $code,
        public ?string $title,
    ) {
    }

    public function isActive(?string $currentRoute = null, array $currentRouteParameters = []): bool
    {
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
