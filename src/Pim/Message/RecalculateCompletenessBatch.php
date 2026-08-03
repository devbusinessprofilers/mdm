<?php

declare(strict_types=1);

namespace App\Pim\Message;

final readonly class RecalculateCompletenessBatch
{
    public function __construct(
        public string $ficheType,
        public int $revision,
        public ?string $afterId = null,
        public int $batchSize = 250,
    ) {
    }
}
