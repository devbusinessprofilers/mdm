<?php

declare(strict_types=1);

namespace App\Shared\EventSubscriber;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Uid\Ulid;

/**
 * Attache un identifiant unique à chaque requête (repris du header X-Request-Id
 * entrant s'il est fourni par un proxy) et le renvoie dans la réponse. Les logs
 * le reprennent via RequestContextProcessor pour corréler une requête complète.
 */
final class RequestIdListener
{
    public const REQUEST_ATTRIBUTE = '_request_id';
    private const HEADER = 'X-Request-Id';

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 512)]
    public function request(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $request = $event->getRequest();
        $incoming = $request->headers->get(self::HEADER, '');
        $requestId = 1 === preg_match('/^[\w.-]{1,64}$/', $incoming) ? $incoming : (string) new Ulid();
        $request->attributes->set(self::REQUEST_ATTRIBUTE, $requestId);
    }

    #[AsEventListener(event: KernelEvents::RESPONSE)]
    public function response(ResponseEvent $event): void
    {
        $requestId = $event->getRequest()->attributes->get(self::REQUEST_ATTRIBUTE);
        if (is_string($requestId)) {
            $event->getResponse()->headers->set(self::HEADER, $requestId);
        }
    }
}
