<?php

declare(strict_types=1);

namespace App\Enrichment\Service;

interface EnrichmentProviderInterface
{
    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function enrich(string $operation, array $payload): array;
}
