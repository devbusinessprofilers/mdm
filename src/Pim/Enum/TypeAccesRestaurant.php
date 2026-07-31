<?php

declare(strict_types=1);

namespace App\Pim\Enum;

enum TypeAccesRestaurant: string
{
    case Aeroport = 'aeroport';
    case Gare = 'gare';
    case Metro = 'metro';
    case Tramway = 'tramway';
    case GrandeVille = 'grande_ville';
}
