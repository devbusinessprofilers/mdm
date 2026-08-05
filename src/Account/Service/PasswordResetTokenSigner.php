<?php

declare(strict_types=1);

namespace App\Account\Service;

use App\Account\Entity\PasswordResetRequest;

final readonly class PasswordResetTokenSigner
{
    public function __construct(private string $key) {}

    public function sign(PasswordResetRequest $request): string
    {
        return hash_hmac('sha256', $this->payload($request), $this->key);
    }

    public function isValid(PasswordResetRequest $request, string $signature): bool
    {
        return '' !== $signature && hash_equals($this->sign($request), $signature);
    }

    private function payload(PasswordResetRequest $request): string
    {
        return $request->id().'|'.$request->user()->id().'|'.$request->expiresAt()->getTimestamp();
    }
}
