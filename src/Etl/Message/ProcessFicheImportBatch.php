<?php

declare(strict_types=1);

namespace App\Etl\Message;

final readonly class ProcessFicheImportBatch
{
    public function __construct(
        public string $jobId,
        public int $fromLine,
    ) {
    }
}
