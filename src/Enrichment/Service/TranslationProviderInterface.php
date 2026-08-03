<?php

declare(strict_types=1);

namespace App\Enrichment\Service;

use App\Enrichment\Enum\SupportedLocale;

interface TranslationProviderInterface
{
    /**
     * @param list<string> $texts
     *
     * @return list<string>
     */
    public function translate(array $texts, SupportedLocale $target): array;
}
