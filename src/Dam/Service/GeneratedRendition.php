<?php

declare(strict_types=1);

namespace App\Dam\Service;

final readonly class GeneratedRendition
{
    public function __construct(
        public string $name,
        public int $width,
        public int $height,
        public string $contents,
    ) {
    }
}
