<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Pim\Entity\Activite\Activite;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Entity\Service\ServiceEvenementiel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/** Jeton CSRF du bloc médias d'une fiche (`lieu-media-{id}`), envoyé en en-tête X-CSRF-TOKEN ou en champ _token. */
final readonly class FicheMediaCsrfGuard
{
    public function __construct(private CsrfTokenManagerInterface $tokens)
    {
    }

    public function assertRequest(Request $request, Lieu|Restaurant|Activite|ServiceEvenementiel $entite): void
    {
        $this->assertValid($entite, (string) $request->headers->get('X-CSRF-TOKEN', $request->request->getString('_token')));
    }

    public function assertValid(Lieu|Restaurant|Activite|ServiceEvenementiel $entite, string $value): void
    {
        if (!$this->tokens->isTokenValid(new CsrfToken('lieu-media-'.$entite->id(), $value))) {
            throw new AccessDeniedException('Jeton CSRF invalide.');
        }
    }
}
