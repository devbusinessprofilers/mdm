<?php

declare(strict_types=1);

namespace App\Etl\Service;

interface SalesforceClientInterface
{
    public function isConfigured(): bool;

    /**
     * Exécute une requête SOQL et retourne l'ensemble des enregistrements
     * (pagination Salesforce suivie automatiquement).
     *
     * @return list<array<string, mixed>>
     *
     * @throws SalesforceApiException
     */
    public function query(string $soql): array;
}
