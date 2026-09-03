<?php

declare(strict_types=1);

namespace App\Enrichment\Message;

final readonly class TranslatePublishedFiche
{
    public function __construct(public string $ficheId, public string $locale, public string $requestToken)
    {
    }
}
