<?php

declare(strict_types=1);

namespace App\Ocr\Message;

final readonly class CleanupBoxFile
{
    public function __construct(public string $extractionId, public string $fileId) {}
}
