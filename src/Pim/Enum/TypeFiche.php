<?php

declare(strict_types=1);

namespace App\Pim\Enum;

enum TypeFiche: string
{
    case Lieu = 'lieu';
    case Restaurant = 'restaurant';
    case Activite = 'activite';
    case ServiceEvenementiel = 'service_evenementiel';
    case Traiteur = 'traiteur';
}
