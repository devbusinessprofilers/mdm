<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use App\Shared\EventSubscriber\SecurityHeadersListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class SecurityHeadersListenerTest extends TestCase
{
    private const S3_BASE_URL = 'https://bp-dam-public.s3.example.test';

    public function testSecurityHeadersAreAddedToWebResponses(): void
    {
        $response = $this->respond(Request::create('/login'));
        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        self::assertSame('DENY', $response->headers->get('X-Frame-Options'));
        self::assertSame('strict-origin-when-cross-origin', $response->headers->get('Referrer-Policy'));
        self::assertSame('camera=(), microphone=(), geolocation=()', $response->headers->get('Permissions-Policy'));
        self::assertFalse($response->headers->has('Strict-Transport-Security'));
        $csp = (string) $response->headers->get('Content-Security-Policy-Report-Only');
        self::assertStringContainsString("default-src 'self'", $csp);
        self::assertStringContainsString(self::S3_BASE_URL, $csp);
        self::assertStringContainsString("frame-ancestors 'none'", $csp);
    }

    public function testHstsIsOnlySentOverHttps(): void
    {
        $response = $this->respond(Request::create('https://mdm.example.test/login'));
        self::assertSame('max-age=31536000; includeSubDomains', $response->headers->get('Strict-Transport-Security'));
    }

    public function testApiResponsesHaveNoCspButKeepNosniff(): void
    {
        $response = $this->respond(Request::create('/api/v1/lieux'));
        self::assertFalse($response->headers->has('Content-Security-Policy-Report-Only'));
        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    private function respond(Request $request): Response
    {
        $response = new Response();
        (new SecurityHeadersListener(self::S3_BASE_URL))->response(new ResponseEvent(
            $this->createStub(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response,
        ));

        return $response;
    }
}
