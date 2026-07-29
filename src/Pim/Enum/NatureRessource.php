<?php

declare(strict_types=1);

namespace App\Pim\Enum;

enum NatureRessource: string
{
    case Photo = 'photo';
    case Document = 'document';
    case Video = 'video';
}
