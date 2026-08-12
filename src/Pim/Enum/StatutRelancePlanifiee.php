<?php

declare(strict_types=1);

namespace App\Pim\Enum;

enum StatutRelancePlanifiee: string
{
    case Planifiee = 'planifiee';
    case Exclue = 'exclue';
    case Envoyee = 'envoyee';
    case Ignoree = 'ignoree';
    case Annulee = 'annulee';

    public function label(): string
    {
        return match ($this) {
            self::Planifiee => 'Planifiée',
            self::Exclue => 'Exclue',
            self::Envoyee => 'Envoyée',
            self::Ignoree => 'Ignorée',
            self::Annulee => 'Annulée',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Planifiee => 'badge-warning',
            self::Exclue => 'badge-neutral',
            self::Envoyee => 'badge-success',
            self::Ignoree => 'badge-neutral',
            self::Annulee => 'badge-danger',
        };
    }
}
