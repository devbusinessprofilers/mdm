<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Pim\Entity\Lieu\Lieu;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final readonly class LieuMediaCsrfGuard
{
    public function __construct(private CsrfTokenManagerInterface $tokens) {}

    public function assertValid(Lieu $lieu, string $value): void
    {
        if (!$this->tokens->isTokenValid(new CsrfToken('lieu-media-'.$lieu->id(), $value))) {
            throw new AccessDeniedException('Jeton CSRF invalide.');
        }
    }
}
