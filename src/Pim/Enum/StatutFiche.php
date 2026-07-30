<?php

declare(strict_types=1);

namespace App\Pim\Enum;

enum StatutFiche: string
{
    case EnCours = 'en_cours';
    case EnAttenteValidation = 'en_attente_validation';
    case Publiee = 'publiee';
    case Archivee = 'archivee';
}
