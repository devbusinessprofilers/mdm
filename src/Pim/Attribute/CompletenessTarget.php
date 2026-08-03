<?php

declare(strict_types=1);

namespace App\Pim\Attribute;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_PROPERTY)]
final readonly class CompletenessTarget
{
    public function __construct(public int $length)
    {
        if ($length < 1) {
            throw new \InvalidArgumentException('La longueur cible doit être strictement positive.');
        }
    }
}
