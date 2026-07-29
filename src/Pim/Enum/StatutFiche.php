<?php

declare(strict_types=1);

namespace App\Pim\Enum;

enum StatutFiche: string
{
    case EnCours = 'en_cours';
    case Validee = 'validee';
    case Publiee = 'publiee';
    case Archivee = 'archivee';
}
