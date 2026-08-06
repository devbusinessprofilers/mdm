<?php

declare(strict_types=1);

namespace App\Shared\RateLimit;

use App\Account\Security\ExternalSitePrincipal;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Limite le trafic de l'API externe (par principal JWT, repli par IP) et des
 * pages publiques sensibles. Priorité 4 : après le firewall (8), le token JWT
 * est donc disponible — les requêtes rejetées en 401 par le firewall ne
 * consomment pas de quota.
 */
final readonly class RateLimitListener
{
    private const RATE_LIMIT_ATTRIBUTE = '_rate_limit';

    public function __construct(
        #[Autowire(service: 'limiter.api_client')]
        private RateLimiterFactory $apiClientLimiter,
        #[Autowire(service: 'limiter.api_ip')]
        private RateLimiterFactory $apiIpLimiter,
        #[Autowire(service: 'limiter.public_endpoint_ip')]
        private RateLimiterFactory $publicEndpointLimiter,
        private TokenStorageInterface $tokenStorage,
    ) {}

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 4)]
    public function request(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $request = $event->getRequest();
        $limiter = $this->limiterFor($request);
        if (null === $limiter) {
            return;
        }
        $limit = $limiter->consume();
        $request->attributes->set(self::RATE_LIMIT_ATTRIBUTE, $limit);
        if ($limit->isAccepted()) {
            return;
        }
        $response = new JsonResponse(
            [
                'type' => 'rate_limit_exceeded',
                'message' => 'Trop de requêtes, réessayez plus tard.',
            ],
            Response::HTTP_TOO_MANY_REQUESTS,
        );
        $response->headers->set('Retry-After', (string) max(1, $limit->getRetryAfter()->getTimestamp() - time()));
        $event->setResponse($response);
    }

    #[AsEventListener(event: KernelEvents::RESPONSE)]
    public function response(ResponseEvent $event): void
    {
        $limit = $event->getRequest()->attributes->get(self::RATE_LIMIT_ATTRIBUTE);
        if (!$limit instanceof RateLimit) {
            return;
        }
        $headers = $event->getResponse()->headers;
        $headers->set('RateLimit-Limit', (string) $limit->getLimit());
        $headers->set('RateLimit-Remaining', (string) $limit->getRemainingTokens());
        $headers->set('RateLimit-Reset', (string) max(0, $limit->getRetryAfter()->getTimestamp() - time()));
    }

    private function limiterFor(Request $request): ?LimiterInterface
    {
        $path = $request->getPathInfo();
        if (str_starts_with($path, '/api/v1/')) {
            $user = $this->tokenStorage->getToken()?->getUser();
            if ($user instanceof ExternalSitePrincipal) {
                return $this->apiClientLimiter->create('client:'.$user->getUserIdentifier());
            }

            return $this->apiIpLimiter->create('ip:'.($request->getClientIp() ?? 'unknown'));
        }
        if (str_starts_with($path, '/invitation/') || '/mot-de-passe-oublie' === $path) {
            return $this->publicEndpointLimiter->create('ip:'.($request->getClientIp() ?? 'unknown'));
        }

        return null;
    }
}
