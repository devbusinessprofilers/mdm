<?php

declare(strict_types=1);

namespace App\Account\Security;

use Symfony\Component\Security\Core\User\UserInterface;

final readonly class ExternalSitePrincipal implements UserInterface
{
    /** @param non-empty-string $subject */
    public function __construct(private string $subject)
    {
    }

    /** @return non-empty-string */
    public function getUserIdentifier(): string { return $this->subject; }
    public function getRoles(): array { return ['ROLE_EXTERNAL_SITE']; }
    public function eraseCredentials(): void {}
}
