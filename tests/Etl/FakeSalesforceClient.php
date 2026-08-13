<?php

declare(strict_types=1);

namespace App\Tests\Etl;

use App\Etl\Service\SalesforceClientInterface;

/** Doublure de test : sert des enregistrements SOQL prédéfinis. */
final class FakeSalesforceClient implements SalesforceClientInterface
{
    /** @var list<string> */
    public array $queries = [];

    /** @param list<array<string, mixed>> $records */
    public function __construct(
        public array $records = [],
        private readonly bool $configured = true,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->configured;
    }

    public function query(string $soql): array
    {
        $this->queries[] = $soql;

        return $this->records;
    }
}
