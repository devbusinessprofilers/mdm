<?php

declare(strict_types=1);

namespace App\Enrichment\Enum;

enum TranslationStatus: string
{
    case Pending = 'en_attente';
    case Processing = 'en_cours';
    case Available = 'disponible';
    case Obsolete = 'obsolete';
    case Failed = 'en_erreur';
}
