<?php

declare(strict_types=1);

namespace App\Shared\Service;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Construit l'état de santé applicatif : 503 uniquement si la base est
 * injoignable ; une queue failed non vide dégrade le statut sans faire
 * échouer la sonde (le healthcheck Docker/Upsun continue de passer).
 */
final readonly class HealthReporter
{
    public function __construct(private Connection $connection) {}

    /** @return array{httpStatus: int, payload: array<string, mixed>} */
    public function report(): array
    {
        $checks = ['db' => 'ok'];
        $status = 'ok';
        $httpStatus = Response::HTTP_OK;
        try {
            $this->connection->executeQuery('SELECT 1');
        } catch (\Throwable) {
            $checks['db'] = 'error';
            $status = 'down';
            $httpStatus = Response::HTTP_SERVICE_UNAVAILABLE;
        }
        if ('ok' === $checks['db']) {
            try {
                $failed = (int) $this->connection->fetchOne(
                    "SELECT COUNT(*) FROM messenger_messages WHERE queue_name = 'failed'",
                );
                $pending = $this->connection->fetchAllKeyValue(
                    "SELECT queue_name, COUNT(*) FROM messenger_messages WHERE delivered_at IS NULL AND queue_name <> 'failed' GROUP BY queue_name",
                );
                $checks['messenger'] = [
                    'failed' => $failed,
                    'pending' => array_map(intval(...), $pending),
                ];
                if ($failed > 0) {
                    $status = 'degraded';
                }
            } catch (\Throwable) {
                $checks['messenger'] = 'error';
                $status = 'degraded';
            }
        }

        return [
            'httpStatus' => $httpStatus,
            'payload' => [
                'application' => 'business-profilers-pim-dam',
                'status' => $status,
                'checks' => $checks,
            ],
        ];
    }
}
