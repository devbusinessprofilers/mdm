<?php

declare(strict_types=1);

namespace App\Etl\Message;

/**
 * Tic périodique (chaque minute) : envoie à Salesforce les fiches modifiées
 * depuis leur dernier envoi Produits (un e-mail par fiche, coalescé).
 */
final readonly class FlushSalesforceExports
{
}
