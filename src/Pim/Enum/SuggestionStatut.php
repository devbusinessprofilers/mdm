<?php

declare(strict_types=1);

namespace App\Pim\Enum;

/** Cycle de vie d'une suggestion générique : en attente, puis arbitrée. */
enum SuggestionStatut: string
{
    case EnAttente = 'en_attente';
    case Acceptee = 'acceptee';
    case Ignoree = 'ignoree';
}
