<?php

declare(strict_types=1);

namespace App\Pim\Enum;

/** Catégories d'accès d'un Service événementiel (bloc Accessibilité de la maquette portail). */
enum TypeAccesService: string
{
    case GrandeVille = 'grande_ville';
    case Parking = 'parking';
    case Gare = 'gare';
    case Aeroport = 'aeroport';

    public function label(): string
    {
        return match ($this) {
            self::GrandeVille => 'Accès par la route',
            self::Parking => 'Parking(s)',
            self::Gare => 'Gare(s)',
            self::Aeroport => 'Aéroport(s)',
        };
    }

    /** @return array<string, self> Libellé => cas, dans l'ordre maquette. */
    public static function choices(): array
    {
        $choices = [];
        foreach (self::cases() as $case) {
            $choices[$case->label()] = $case;
        }

        return $choices;
    }
}
