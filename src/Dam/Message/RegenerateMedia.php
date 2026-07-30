<?php

declare(strict_types=1);

namespace App\Dam\Message;

final readonly class RegenerateMedia
{
    public function __construct(public string $mediaId)
    {
    }
}
