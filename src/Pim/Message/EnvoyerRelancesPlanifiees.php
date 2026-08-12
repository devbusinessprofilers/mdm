<?php

declare(strict_types=1);

namespace App\Pim\Message;

/**
 * Fan-out d'envoi du lot de relances de complétude planifié : cron du lundi
 * 14h (force = false, respecte le paramètre completude.rappel_auto_actif) ou
 * bouton « Envoyer maintenant » du dashboard (force = true).
 */
final readonly class EnvoyerRelancesPlanifiees
{
    public function __construct(
        public bool $force = false,
    ) {
    }
}
