<?php

declare(strict_types=1);

namespace App\Pim\Enum;

enum CompletenessFormula: string
{
    case Presence = 'presence';
    case LengthRatio = 'length_ratio';

    public function label(): string
    {
        return match ($this) {
            self::Presence => 'Rempli ou non',
            self::LengthRatio => 'Pourcentage de longueur',
        };
    }
}
