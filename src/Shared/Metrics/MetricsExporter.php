<?php

declare(strict_types=1);

namespace App\Shared\Metrics;

use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;

/**
 * Contrôle d'accès (jeton Bearer METRICS_TOKEN) et rendu Prometheus
 * (format texte 0.0.4) des métriques applicatives.
 */
final readonly class MetricsExporter
{
    public function __construct(
        private MetricsCollector $metrics,
        private Connection $connection,
        #[Autowire(env: 'METRICS_TOKEN')]
        private string $token,
    ) {}

    public function isAuthorized(Request $request): bool
    {
        if ('' === $this->token) {
            return false;
        }
        if (!preg_match('/^Bearer\s+(\S+)$/i', $request->headers->get('Authorization', ''), $matches)) {
            return false;
        }

        return hash_equals($this->token, $matches[1]);
    }

    public function render(): string
    {
        $lines = [
            '# TYPE app_http_requests_total counter',
            '# TYPE app_http_request_seconds_sum counter',
            '# TYPE app_http_request_seconds_count counter',
            '# TYPE app_messenger_messages_total counter',
            '# TYPE app_messenger_message_seconds_sum counter',
            '# TYPE app_messenger_message_seconds_count counter',
        ];
        foreach ($this->metrics->all() as $key => $value) {
            if (1 === preg_match('/^requests_total\.(\w+)\.(\dxx)$/', $key, $matches)) {
                $lines[] = sprintf('app_http_requests_total{group="%s",status_class="%s"} %s', $matches[1], $matches[2], $this->format($value));
            } elseif ('request_seconds_sum' === $key) {
                $lines[] = 'app_http_request_seconds_sum '.$this->format($value);
            } elseif ('request_seconds_count' === $key) {
                $lines[] = 'app_http_request_seconds_count '.$this->format($value);
            } elseif (1 === preg_match('/^messages_total\.(\w+)\.(\w+)$/', $key, $matches)) {
                $lines[] = sprintf('app_messenger_messages_total{message="%s",outcome="%s"} %s', $matches[1], $matches[2], $this->format($value));
            } elseif (1 === preg_match('/^message_seconds_sum\.(\w+)$/', $key, $matches)) {
                $lines[] = sprintf('app_messenger_message_seconds_sum{message="%s"} %s', $matches[1], $this->format($value));
            } elseif (1 === preg_match('/^message_seconds_count\.(\w+)$/', $key, $matches)) {
                $lines[] = sprintf('app_messenger_message_seconds_count{message="%s"} %s', $matches[1], $this->format($value));
            }
        }
        $lines[] = '# TYPE app_messenger_queue_messages gauge';
        $lines[] = '# TYPE app_messenger_queue_oldest_message_seconds gauge';
        $lines[] = '# TYPE app_messenger_failed_messages gauge';
        try {
            $pending = $this->connection->fetchAllKeyValue(
                "SELECT queue_name, COUNT(*) FROM messenger_messages WHERE delivered_at IS NULL AND queue_name <> 'failed' GROUP BY queue_name",
            );
            foreach ($pending as $queue => $count) {
                $lines[] = sprintf('app_messenger_queue_messages{queue="%s"} %d', $queue, (int) $count);
            }
            // available_at est stocké en UTC par le transport Doctrine, d'où
            // UTC_TIMESTAMP() quel que soit le fuseau du serveur MySQL.
            $oldest = $this->connection->fetchAllKeyValue(
                "SELECT queue_name, TIMESTAMPDIFF(SECOND, MIN(available_at), UTC_TIMESTAMP()) FROM messenger_messages WHERE delivered_at IS NULL AND queue_name <> 'failed' GROUP BY queue_name",
            );
            foreach ($oldest as $queue => $seconds) {
                $lines[] = sprintf('app_messenger_queue_oldest_message_seconds{queue="%s"} %d', $queue, max(0, (int) $seconds));
            }
            $lines[] = 'app_messenger_failed_messages '.(int) $this->connection->fetchOne(
                "SELECT COUNT(*) FROM messenger_messages WHERE queue_name = 'failed'",
            );
        } catch (\Throwable) {
            $lines[] = '# messenger metrics unavailable (database error)';
        }

        return implode("\n", $lines)."\n";
    }

    private function format(int|float $value): string
    {
        return is_int($value) ? (string) $value : rtrim(rtrim(sprintf('%.6f', $value), '0'), '.');
    }
}
