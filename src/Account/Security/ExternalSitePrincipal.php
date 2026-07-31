<?php

declare(strict_types=1);

namespace App\Account\Security;

use Symfony\Component\Security\Core\User\UserInterface;

final readonly class ExternalSitePrincipal implements UserInterface
{
    /** @param non-empty-string $subject
     *  @param list<string> $scopes
     */
    public function __construct(private string $subject, private array $scopes = [])
    {
    }

    /** @return non-empty-string */
    public function getUserIdentifier(): string { return $this->subject; }
    public function getRoles(): array { return ['ROLE_EXTERNAL_SITE']; }
    /** @return list<string> */
    public function scopes(): array { return $this->scopes; }
    public function hasScope(string $scope): bool { return in_array($scope, $this->scopes, true); }
    public function eraseCredentials(): void {}
}
