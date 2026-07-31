<?php

declare(strict_types=1);

namespace App\Pim\Api\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class MediaOrderInput
{
    /** @param list<string> $ids */
    public function __construct(
        #[Assert\Count(min: 1)] public array $ids = [],
    ) {
    }
}
