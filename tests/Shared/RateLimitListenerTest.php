<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use App\Account\Security\ExternalSitePrincipal;
use App\Shared\RateLimit\RateLimitListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;

final class RateLimitListenerTest extends TestCase
{
    private RateLimitListener $listener;
    private TokenStorage $tokenStorage;

    protected function setUp(): void
    {
        $storage = new InMemoryStorage();
        $factory = static fn (string $id, int $limit): RateLimiterFactory => new RateLimiterFactory(
            ['id' => $id, 'policy' => 'fixed_window', 'limit' => $limit, 'interval' => '1 hour'],
            $storage,
        );
        $this->tokenStorage = new TokenStorage();
        $this->listener = new RateLimitListener(
            $factory('api_client', 3),
            $factory('api_ip', 2),
            $factory('public_endpoint_ip', 2),
            $this->tokenStorage,
        );
    }

    public function testApiRequestsAreLimitedPerJwtPrincipal(): void
    {
        $this->authenticate('site-a');
        self::assertNull($this->request('/api/v1/lieux')->getResponse());
        self::assertNull($this->request('/api/v1/lieux')->getResponse());
        self::assertNull($this->request('/api/v1/restaurants')->getResponse());
        $rejected = $this->request('/api/v1/lieux')->getResponse();
        self::assertInstanceOf(Response::class, $rejected);
        self::assertSame(429, $rejected->getStatusCode());
        self::assertTrue($rejected->headers->has('Retry-After'));
        $body = json_decode((string) $rejected->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('rate_limit_exceeded', $body['type']);

        // Un autre principal garde son propre compteur.
        $this->authenticate('site-b');
        self::assertNull($this->request('/api/v1/lieux')->getResponse());
    }

    public function testApiRequestsWithoutPrincipalFallBackToIp(): void
    {
        self::assertNull($this->request('/api/v1/lieux')->getResponse());
        self::assertNull($this->request('/api/v1/lieux')->getResponse());
        self::assertNotNull($this->request('/api/v1/lieux')->getResponse());
    }

    public function testPublicEndpointsAreLimitedPerIp(): void
    {
        self::assertNull($this->request('/mot-de-passe-oublie')->getResponse());
        self::assertNull($this->request('/invitation/abc')->getResponse());
        self::assertNotNull($this->request('/mot-de-passe-oublie')->getResponse());
    }

    public function testOtherPathsAndSubRequestsAreIgnored(): void
    {
        $event = $this->request('/login');
        self::assertNull($event->getResponse());
        self::assertFalse($event->getRequest()->attributes->has('_rate_limit'));

        $sub = $this->request('/api/v1/lieux', HttpKernelInterface::SUB_REQUEST);
        self::assertFalse($sub->getRequest()->attributes->has('_rate_limit'));
    }

    public function testResponseListenerExposesRateLimitHeaders(): void
    {
        $event = $this->request('/api/v1/lieux');
        $response = new Response();
        $this->listener->response(new ResponseEvent(
            $this->createStub(HttpKernelInterface::class),
            $event->getRequest(),
            HttpKernelInterface::MAIN_REQUEST,
            $response,
        ));
        self::assertSame('2', $response->headers->get('RateLimit-Limit'));
        self::assertSame('1', $response->headers->get('RateLimit-Remaining'));
        self::assertTrue($response->headers->has('RateLimit-Reset'));
    }

    private function authenticate(string $subject): void
    {
        $this->tokenStorage->setToken(new PostAuthenticationToken(
            new ExternalSitePrincipal($subject),
            'external_api',
            ['ROLE_EXTERNAL_SITE'],
        ));
    }

    private function request(string $path, int $type = HttpKernelInterface::MAIN_REQUEST): RequestEvent
    {
        $request = Request::create($path);
        $event = new RequestEvent($this->createStub(HttpKernelInterface::class), $request, $type);
        $this->listener->request($event);

        return $event;
    }
}
