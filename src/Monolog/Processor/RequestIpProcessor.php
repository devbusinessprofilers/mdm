<?php

namespace App\Monolog\Processor;

use Symfony\Component\HttpFoundation\RequestStack;
use Monolog\LogRecord;

class RequestIpProcessor
{
    private RequestStack $requestStack;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        $request = $this->requestStack->getCurrentRequest();
        $ip = $request ? $request->getClientIp() ?? 'CLI' : 'CLI';

        // Ajoute l'IP dans extra (comme avant)
        $record = $record->with(extra: array_merge($record->extra, ['ip' => $ip]));

        // Et ajoute aussi dans le message, si tu veux que ce soit lisible directement
        $record = $record->with(message: "[IP: $ip] {$record->message}");

        return $record;
    }
}
