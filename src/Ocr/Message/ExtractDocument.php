<?php

declare(strict_types=1);

namespace App\Ocr\Message;

final readonly class ExtractDocument
{
    public function __construct(public string $extractionId)
    {
    }
}
