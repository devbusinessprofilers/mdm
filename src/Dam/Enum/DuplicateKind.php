<?php

declare(strict_types=1);

namespace App\Dam\Enum;

enum DuplicateKind: string
{
    case Exact = 'exact';
    case Perceptual = 'perceptual';
}
