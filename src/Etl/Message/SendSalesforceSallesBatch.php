<?php

declare(strict_types=1);

namespace App\Etl\Message;

/**
 * Envoi nocturne groupé des salles vers Salesforce : un unique e-mail « Salles »
 * (CSV multi-lignes) pour toutes les fiches modifiées depuis le dernier envoi.
 */
final readonly class SendSalesforceSallesBatch
{
}
