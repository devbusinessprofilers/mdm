<?php

declare(strict_types=1);

namespace App\Shared\EventSubscriber;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Headers de sécurité sur toutes les réponses. La CSP est en Report-Only :
 * l'asset-mapper injecte une importmap inline et les templates utilisent du JS
 * inline — passage en mode bloquant seulement après observation des violations.
 */
final readonly class SecurityHeadersListener
{
    public function __construct(
        #[Autowire(env: 'S3_PUBLIC_BASE_URL')]
        private string $s3PublicBaseUrl,
    ) {
    }

    #[AsEventListener(event: KernelEvents::RESPONSE)]
    public function response(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $request = $event->getRequest();
        $headers = $event->getResponse()->headers;
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }
        if (!str_starts_with($request->getPathInfo(), '/api/')) {
            $headers->set(
                'Content-Security-Policy-Report-Only',
                sprintf(
                    "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: %s; frame-ancestors 'none'",
                    $this->s3PublicBaseUrl,
                ),
            );
        }
    }
}
