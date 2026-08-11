<?php

declare(strict_types=1);

namespace App\Etl\Message;

/**
 * Demande l'envoi (création ou mise à jour) de la fiche vers la marketplace.
 * Le payload est reconstruit à l'exécution : le message ne transporte que
 * l'identifiant, un rejeu envoie donc toujours l'état le plus récent.
 */
final readonly class SyncFicheMarketplace
{
    public function __construct(public string $ficheId)
    {
    }
}
