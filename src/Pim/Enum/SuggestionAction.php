<?php

declare(strict_types=1);

namespace App\Pim\Enum;

/**
 * Nature de la décision appliquée quand une suggestion générique est acceptée :
 * remplir un champ de la fiche, ou proposer une transition de workflow
 * (archivage d'un établissement détecté comme cessé).
 */
enum SuggestionAction: string
{
    case RemplirChamp = 'remplir_champ';
    case Archiver = 'archiver';
}
