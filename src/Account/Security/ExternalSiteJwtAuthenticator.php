<?php

declare(strict_types=1);

namespace App\Account\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

final class ExternalSiteJwtAuthenticator extends AbstractAuthenticator
{
    public function __construct(private readonly ExternalSiteJwtVerifier $verifier) {}

    public function supports(Request $request): bool { return str_starts_with($request->getPathInfo(), '/api/v1/lieux'); }

    public function authenticate(Request $request): SelfValidatingPassport
    {
        if (!preg_match('/^Bearer\s+(\S+)$/i', $request->headers->get('Authorization', ''), $matches)) {
            throw new BadCredentialsException('Jeton Bearer manquant.');
        }
        $claims = $this->verifier->verify($matches[1]);
        $subject = (string) $claims['sub'];
        if ('' === $subject) { throw new BadCredentialsException('Identité JWT vide.'); }
        return new SelfValidatingPassport(new UserBadge($subject, static function (string $id): ExternalSitePrincipal {
            if ('' === $id) { throw new BadCredentialsException('Identité JWT vide.'); }
            return new ExternalSitePrincipal($id);
        }));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response { return null; }
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return new JsonResponse(['type' => 'invalid_token', 'message' => 'Authentification du site externe invalide.'], Response::HTTP_UNAUTHORIZED);
    }
}
