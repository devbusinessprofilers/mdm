<?php

declare(strict_types=1);

namespace App\Dam\Enum;

enum RightsValidityStatus: string
{
    case NotGranted = 'not_granted';
    case Unlimited = 'unlimited';
    case Valid = 'valid';
    case Expiring = 'expiring';
    case Expired = 'expired';
}
