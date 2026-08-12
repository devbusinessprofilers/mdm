<?php

declare(strict_types=1);

namespace App\Tests\Etl;

use App\Etl\Service\MarketplaceClientInterface;

/** Doublure de test : enregistre les appels au lieu de joindre la marketplace. */
final class RecordingMarketplaceClient implements MarketplaceClientInterface
{
    /** @var list<array{code: int, payload: array<string, mixed>}> */
    public array $upserts = [];

    /** @var list<array{code: int, sequence: string}> */
    public array $removals = [];

    /** @var list<array<string, mixed>> */
    public array $lovUpserts = [];

    /** @var list<array{code: int, sequence: string, locations: list<string>}> */
    public array $prunes = [];

    /** @var array{removed: int, remaining: int, principaleRemaining: bool}|false|null */
    public array|false|null $pruneResult = ['removed' => 0, 'remaining' => 4, 'principaleRemaining' => true];

    public function __construct(private readonly bool $configured = true)
    {
    }

    public function isConfigured(): bool
    {
        return $this->configured;
    }

    public function upsertFiche(int $code, array $payload): bool
    {
        $this->upserts[] = ['code' => $code, 'payload' => $payload];

        return true;
    }

    public function upsertLovDictionary(array $payload): bool
    {
        $this->lovUpserts[] = $payload;

        return true;
    }

    public function removeFiche(int $code, string $sequence): bool
    {
        $this->removals[] = ['code' => $code, 'sequence' => $sequence];

        return true;
    }

    public function pruneFichePhotos(int $code, string $sequence, array $locations): array|false|null
    {
        $this->prunes[] = ['code' => $code, 'sequence' => $sequence, 'locations' => $locations];

        return $this->pruneResult;
    }
}
