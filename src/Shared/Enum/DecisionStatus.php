<?php

declare(strict_types=1);

namespace App\Shared\Enum;

/**
 * État d'une suggestion soumise à arbitrage (extraction OCR, reconnaissance
 * d'image) : en attente, acceptée ou refusée. Valeurs stockées inchangées.
 */
enum DecisionStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
}
