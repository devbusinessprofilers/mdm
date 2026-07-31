<?php

declare(strict_types=1);

namespace App\Pim\Enum;

enum TypeOffreActivite: string
{
    case Forfait = 'forfait';
    case Option = 'option';
}
