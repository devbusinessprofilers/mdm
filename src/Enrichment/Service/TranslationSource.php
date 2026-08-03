<?php

declare(strict_types=1);

namespace App\Enrichment\Service;

final readonly class TranslationSource
{
    public function __construct(public string $path, public string $label, public string $value) {}
}
