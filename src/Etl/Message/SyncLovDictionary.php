<?php

declare(strict_types=1);

namespace App\Etl\Message;

/**
 * Pousse le snapshot complet du dictionnaire LOV vers la marketplace. Le
 * message est vide : le dictionnaire est relu à l'exécution, un message en
 * retard pousse donc toujours l'état courant.
 */
final readonly class SyncLovDictionary
{
}
