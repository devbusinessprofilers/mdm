<?php

namespace App\Pim\Model\ProviderPortal\Pagination;

class PaginationItem
{
    public bool $isCurrent = false;
    public bool $isSpacer = false;

    public function __construct(
        public string $label,
        public ?int $page = null,
    ) {
    }

    public function setIsCurrent(bool $isCurrent): static
    {
        $this->isCurrent = $isCurrent;

        return $this;
    }

    public function setIsSpacer(bool $isSpacer): static
    {
        $this->isSpacer = $isSpacer;

        return $this;
    }
}
