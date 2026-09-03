<?php

declare(strict_types=1);

namespace App\Account\Service;

use App\Account\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final readonly class CurrentActorProvider
{
    public function __construct(private Security $security)
    {
    }

    public function id(): string
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedException();
        }

        return $user->id();
    }
}
