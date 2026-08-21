<?php

declare(strict_types=1);

namespace App\Pim\Enum;

/**
 * Cycle d'arbitrage d'une alerte de doublon de texte, calqué sur celui des
 * doublons photos du DAM (App\Dam\Enum\DuplicateReviewStatus) — dupliqué côté
 * Pim pour ne pas coupler Pim au module Dam.
 */
enum DuplicateReviewStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Resolved = 'resolved';
}
