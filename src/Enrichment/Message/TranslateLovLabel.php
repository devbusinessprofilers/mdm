<?php

declare(strict_types=1);

namespace App\Enrichment\Message;

final readonly class TranslateLovLabel
{
    public function __construct(public string $subject, public int $subjectId, public string $locale, public string $requestToken) {}
}
