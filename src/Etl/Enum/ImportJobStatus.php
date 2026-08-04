<?php

declare(strict_types=1);

namespace App\Etl\Enum;

enum ImportJobStatus: string
{
    case EnAttente = 'en_attente';
    case EnCours = 'en_cours';
    case Termine = 'termine';
    case TermineAvecErreurs = 'termine_avec_erreurs';
    case Echoue = 'echoue';

    public function label(): string
    {
        return match ($this) {
            self::EnAttente => 'En attente',
            self::EnCours => 'En cours',
            self::Termine => 'Terminé',
            self::TermineAvecErreurs => 'Terminé avec erreurs',
            self::Echoue => 'Échoué',
        };
    }
}
