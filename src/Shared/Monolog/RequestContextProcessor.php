<?php

declare(strict_types=1);

namespace App\Shared\Monolog;

use App\Shared\EventSubscriber\RequestIdListener;
use Monolog\Attribute\AsMonologProcessor;
use Monolog\LogRecord;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/** Corrèle chaque ligne de log avec la requête HTTP en cours. */
#[AsMonologProcessor]
final readonly class RequestContextProcessor
{
    public function __construct(
        private RequestStack $requestStack,
        private TokenStorageInterface $tokenStorage,
    ) {}

    public function __invoke(LogRecord $record): LogRecord
    {
        $request = $this->requestStack->getMainRequest();
        if (null === $request) {
            return $record;
        }
        $record->extra['request_id'] = $request->attributes->get(RequestIdListener::REQUEST_ATTRIBUTE);
        $record->extra['method'] = $request->getMethod();
        $record->extra['path'] = $request->getPathInfo();
        $record->extra['route'] = $request->attributes->get('_route');
        $record->extra['ip'] = $request->getClientIp();
        $user = $this->tokenStorage->getToken()?->getUserIdentifier();
        if (null !== $user && '' !== $user) {
            $record->extra['user'] = $user;
        }

        return $record;
    }
}
