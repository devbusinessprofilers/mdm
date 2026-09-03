<?php

declare(strict_types=1);

namespace App\Shared\Metrics;

use App\Shared\Alert\AlertNotifier;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Alimente les métriques HTTP après l'envoi de la réponse (kernel.terminate,
 * hors chemin critique) et ne journalise que les requêtes lentes ou en erreur
 * serveur — le handler fingers_crossed de prod laisse passer ces niveaux.
 */
final readonly class RequestTimingListener
{
    private const SLOW_REQUEST_SECONDS = 1.0;
    private const SERVER_ERROR_ALERT_THRESHOLD = 10;
    private const SERVER_ERROR_WINDOW_SECONDS = 300;

    public function __construct(
        private MetricsCollector $metrics,
        private LoggerInterface $logger,
        private AlertNotifier $alertNotifier,
        #[Autowire(service: 'cache.metrics')]
        private CacheItemPoolInterface $cache,
    ) {
    }

    #[AsEventListener(event: KernelEvents::TERMINATE)]
    public function terminate(TerminateEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $request = $event->getRequest();
        $status = $event->getResponse()->getStatusCode();
        $start = $request->server->get('REQUEST_TIME_FLOAT');
        $seconds = is_numeric($start) ? max(0.0, microtime(true) - (float) $start) : 0.0;
        $this->metrics->recordRequest($this->group($request), $status, $seconds);

        if ($status >= 500) {
            $this->logger->error('http.request.server_error', ['status' => $status, 'duration_s' => round($seconds, 3)]);
            $this->countServerError();
        } elseif ($seconds > self::SLOW_REQUEST_SECONDS) {
            $this->logger->warning('http.request.slow', ['status' => $status, 'duration_s' => round($seconds, 3)]);
        }
    }

    private function countServerError(): void
    {
        $item = $this->cache->getItem('server_error_window');
        $count = is_int($item->get()) ? $item->get() + 1 : 1;
        $item->set($count);
        $item->expiresAfter(self::SERVER_ERROR_WINDOW_SECONDS);
        $this->cache->save($item);
        if ($count >= self::SERVER_ERROR_ALERT_THRESHOLD) {
            $this->alertNotifier->notify(
                'http_5xx',
                'server_error_window',
                sprintf('%d erreurs 5xx en %d minutes', $count, intdiv(self::SERVER_ERROR_WINDOW_SECONDS, 60)),
                sprintf(
                    "L'application a renvoyé %d réponses 5xx dans les %d dernières minutes.\nConsulter les logs (stderr JSON, corréler via extra.request_id).",
                    $count,
                    intdiv(self::SERVER_ERROR_WINDOW_SECONDS, 60),
                ),
            );
        }
    }

    private function group(Request $request): string
    {
        $path = $request->getPathInfo();

        return match (true) {
            str_starts_with($path, '/api/') => 'api',
            str_starts_with($path, '/admin') => 'admin',
            default => 'other',
        };
    }
}
