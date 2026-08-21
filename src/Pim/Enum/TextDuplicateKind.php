<?php

declare(strict_types=1);

namespace App\Pim\Enum;

enum TextDuplicateKind: string
{
    /** Textes identiques une fois normalisés (copier-coller intégral). */
    case Exact = 'exact';

    /** Textes proches (SimHash à faible distance de Hamming). */
    case Near = 'near';
}
