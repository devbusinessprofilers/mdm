<?php

declare(strict_types=1);

namespace App\Pim\Import\Schema;

enum ColumnKind
{
    case Text;
    case Int;
    case Bool;
    case Decimal;
    case Float;
    case Date;
    case Time;
    case Enum;
    case LovMono;
    case LovMulti;
    case StringList;
    case Prestataire;
    case SitesDiffusion;
}
