<?php

declare(strict_types=1);

namespace App\Account\Enum;

enum FicheAffiliationRole: string
{
    case Manager = 'manager';
    case Administrateur = 'administrateur';
    case Utilisateur = 'utilisateur';

    public function label(): string
    {
        return match ($this) {
            self::Manager => 'Manager',
            self::Administrateur => 'Administrateur',
            self::Utilisateur => 'Utilisateur',
        };
    }
}
