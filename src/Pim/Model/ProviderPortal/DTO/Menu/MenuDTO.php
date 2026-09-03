<?php

namespace App\Pim\Model\ProviderPortal\DTO\Menu;

class MenuDTO
{
    /** @var list<MenuDTOItem> */
    public array $items = [];

    /** @var list<MenuDTOSection> */
    public array $sections = [];

    public function addSection(MenuDTOSection $section): static
    {
        $this->sections[] = $section;

        return $this;
    }

    /** @return list<MenuDTOSection> */
    public function getSections(): array
    {
        return $this->sections;
    }

    public function getSection(string $code): ?MenuDTOSection
    {
        foreach ($this->sections as $section) {
            if ($section->code === $code) {
                return $section;
            }
        }

        return null;
    }

    public function addItem(MenuDTOItem $item): static
    {
        $this->items[] = $item;

        return $this;
    }

    /** @return list<MenuDTOItem> */
    public function getItems(): array
    {
        return $this->items;
    }

    public function getItem(string $code): ?MenuDTOItem
    {
        foreach ($this->items as $item) {
            if ($item->code === $code) {
                return $item;
            }

            $founded = $item->getItem($code);
            if (!$founded) {
                continue;
            }

            return $founded;
        }

        foreach ($this->sections as $section) {
            $item = $section->getItem($code);
            if (null !== $item) {
                return $item;
            }
        }

        return null;
    }
}
