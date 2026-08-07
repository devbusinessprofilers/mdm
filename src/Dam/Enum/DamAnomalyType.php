<?php

declare(strict_types=1);

namespace App\Dam\Enum;

enum DamAnomalyType: string
{
    // La ressource pointe vers un média DAM supprimé ou introuvable.
    case OrphanResource = 'orphan_resource';
    // Média « processed » dont certaines variantes n'existent pas.
    case MissingRenditions = 'missing_renditions';
}
