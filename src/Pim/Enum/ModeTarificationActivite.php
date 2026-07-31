<?php

declare(strict_types=1);

namespace App\Pim\Enum;

enum ModeTarificationActivite: string
{
    case ParPersonne = 'par_personne';
    case Forfait = 'forfait';
}
