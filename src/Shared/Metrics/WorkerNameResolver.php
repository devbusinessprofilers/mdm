<?php

declare(strict_types=1);

namespace App\Shared\Metrics;

/**
 * Traduit l'ensemble des transports consommés par un processus en nom de
 * service docker-compose (worker-pim, worker-batch…) : c'est la seule
 * information dont dispose un worker pour se nommer, le conteneur n'ayant pas
 * accès à son propre nom de service.
 */
final class WorkerNameResolver
{
    private const KNOWN = [
        'pim' => 'worker-pim',
        'dam' => 'worker-dam',
        'completeness,enrichment,etl,marketplace' => 'worker-batch',
        'mail' => 'worker-mail',
        'outbox' => 'worker-outbox',
    ];

    /**
     * @param list<string> $transports
     */
    public function resolve(array $transports): string
    {
        foreach ($transports as $transport) {
            if (str_starts_with($transport, 'scheduler_')) {
                return 'cron-scheduler';
            }
        }
        $sorted = $transports;
        sort($sorted);

        return self::KNOWN[implode(',', $sorted)] ?? 'worker-'.implode('+', $sorted);
    }
}
