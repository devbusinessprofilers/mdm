<?php

declare(strict_types=1);

namespace App\Account\Service;

use App\Account\Entity\AccountInvitation;

final readonly class InvitationTokenSigner
{
    public function __construct(private string $key) {}

    public function sign(AccountInvitation $invitation): string
    {
        return hash_hmac('sha256', $this->payload($invitation), $this->key);
    }

    public function isValid(AccountInvitation $invitation, string $signature): bool
    {
        return '' !== $signature && hash_equals($this->sign($invitation), $signature);
    }

    private function payload(AccountInvitation $invitation): string
    {
        return $invitation->id().'|'.$invitation->user()->id().'|'.$invitation->expiresAt()->getTimestamp();
    }
}
