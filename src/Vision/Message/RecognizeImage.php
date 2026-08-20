<?php

declare(strict_types=1);

namespace App\Vision\Message;

final readonly class RecognizeImage
{
    public function __construct(public string $recognitionId)
    {
    }
}
