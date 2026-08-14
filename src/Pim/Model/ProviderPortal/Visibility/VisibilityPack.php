<?php

namespace App\Pim\Model\ProviderPortal\Visibility;

use App\Pim\Enum\ProviderPortal\VisibilityPackEnum;

class VisibilityPack
{
    /** @var string[] */
    public array $options = [];
    public bool $isSelected = false;
    public int $value;
    public string $action;

    public function __construct(
        public VisibilityPackEnum $type,
    ) {
        $this->value = match ($type) {
            VisibilityPackEnum::FREE => 0,
            VisibilityPackEnum::ESSENTIAL => 33,
            VisibilityPackEnum::PERFORMANCE => 67,
            VisibilityPackEnum::TARGETED_AUDIENCE => 100,
        };
    }

    public function addOption(string $option): static
    {
        $this->options[] = $option;

        return $this;
    }

    public function setIsSelected(bool $isSelected = true): static
    {
        $this->isSelected = $isSelected;

        return $this;
    }

    public function setAction(string $action): static
    {
        $this->action = $action;

        return $this;
    }
}
