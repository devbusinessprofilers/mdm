<?php

declare(strict_types=1);

namespace App\Account\Message;

final readonly class InternalUserInvited
{
    public function __construct(public string $invitationId)
    {
    }
}
