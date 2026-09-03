<?php

declare(strict_types=1);

namespace App\Account\Message;

final readonly class InternalUserPasswordResetRequested
{
    public function __construct(public string $requestId)
    {
    }
}
