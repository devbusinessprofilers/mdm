<?php

namespace App\Pim\Model\ProviderPortal\DTO;

class SecurityDTO
{
    public ?string $password = null;

    public ?string $newPassword = null;

    public static function mock(): self
    {
        return new self();
    }
}
