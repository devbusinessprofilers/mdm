<?php

declare(strict_types=1);

namespace App\Etl\Service;

interface SalesforceClientInterface
{
    public function isConfigured(): bool;

    /**
     * Exécute une requête SOQL et retourne l'ensemble des enregistrements
     * (pagination Salesforce suivie automatiquement). À réserver aux résultats
     * de taille bornée : tout le jeu est chargé en mémoire. Pour un balayage
     * volumineux, préférer queryStream().
     *
     * @return list<array<string, mixed>>
     *
     * @throws SalesforceApiException
     */
    public function query(string $soql): array;

    /**
     * Exécute une requête SOQL et itère les enregistrements page par page :
     * une seule page Salesforce est en mémoire à la fois, quelle que soit la
     * volumétrie totale.
     *
     * @return iterable<array<string, mixed>>
     *
     * @throws SalesforceApiException
     */
    public function queryStream(string $soql): iterable;
}
