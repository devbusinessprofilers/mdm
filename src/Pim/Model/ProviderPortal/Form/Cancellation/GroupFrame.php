<?php

namespace App\Pim\Model\ProviderPortal\Form\Cancellation;

class GroupFrame
{
    public int $frameNumber = 0;
    public readonly int $groupValue;

    public function __construct(
        public readonly int $value,
    ) {
        $this->groupValue = (int) floor($value / 10) * 10;
    }

    public function increment(): void
    {
        ++$this->frameNumber;
    }
}
