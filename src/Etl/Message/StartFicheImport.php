<?php

declare(strict_types=1);

namespace App\Etl\Message;

final readonly class StartFicheImport
{
    public function __construct(public string $jobId)
    {
    }
}
