<?php

namespace App\Pim\Model\ProviderPortal\Form\Tag;

class TagOption
{
    public function __construct(
        public string $value,
        public string $label,
        public ?string $icon = null,
    ) {
    }
}
