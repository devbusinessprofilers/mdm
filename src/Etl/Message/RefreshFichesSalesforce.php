<?php

declare(strict_types=1);

namespace App\Etl\Message;

/**
 * Déclenche le rafraîchissement Salesforce des fiches : complet pour le cron
 * quotidien (Schedule, code null), ciblé pour le webhook produit. Seul le
 * code voyage : l'état est relu chez Salesforce à l'exécution, un rejeu
 * applique donc toujours les données les plus récentes.
 */
final readonly class RefreshFichesSalesforce
{
    /** @param ?int $code Code fiche (Product2.ID__c), null = toutes les fiches. */
    public function __construct(public ?int $code = null)
    {
    }
}
