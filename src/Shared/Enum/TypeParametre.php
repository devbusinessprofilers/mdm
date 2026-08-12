<?php

declare(strict_types=1);

namespace App\Shared\Enum;

enum TypeParametre: string
{
    case Booleen = 'bool';
    case Entier = 'int';
    case Texte = 'string';

    public function label(): string
    {
        return match ($this) {
            self::Booleen => 'Interrupteur',
            self::Entier => 'Nombre entier',
            self::Texte => 'Texte',
        };
    }
}
