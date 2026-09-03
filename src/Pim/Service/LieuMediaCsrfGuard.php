<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Pim\Entity\Activite\Activite;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Entity\Service\ServiceEvenementiel;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final readonly class LieuMediaCsrfGuard
{
    public function __construct(private CsrfTokenManagerInterface $tokens)
    {
    }

    public function assertValid(Lieu|Restaurant|Activite|ServiceEvenementiel $lieu, string $value): void
    {
        if (!$this->tokens->isTokenValid(new CsrfToken('lieu-media-'.$lieu->id(), $value))) {
            throw new AccessDeniedException('Jeton CSRF invalide.');
        }
    }
}
