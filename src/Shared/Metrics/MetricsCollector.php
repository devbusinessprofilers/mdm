<?php

declare(strict_types=1);

namespace App\Shared\Metrics;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Compteurs applicatifs persistés dans le pool cache.metrics et exposés par
 * /metrics. Les incréments ne sont pas atomiques sous l'adapter filesystem :
 * acceptable pour des compteurs indicatifs (Redis les fiabilise si besoin).
 */
final readonly class MetricsCollector
{
    private const INDEX_KEY = 'metrics_index';
    private const PREFIX = 'metrics_';

    public function __construct(
        #[Autowire(service: 'cache.metrics')]
        private CacheItemPoolInterface $cache,
    ) {
    }

    public function recordRequest(string $group, int $statusCode, float $seconds): void
    {
        $statusClass = min(5, max(1, intdiv($statusCode, 100))).'xx';
        $this->add('requests_total.'.$group.'.'.$statusClass, 1);
        $this->add('request_seconds_sum', $seconds);
        $this->add('request_seconds_count', 1);
    }

    public function recordMessage(string $messageClass, string $outcome, float $seconds): void
    {
        $short = false !== ($pos = strrpos($messageClass, '\\')) ? substr($messageClass, $pos + 1) : $messageClass;
        $this->add('messages_total.'.$short.'.'.$outcome, 1);
        $this->add('message_seconds_sum.'.$short, $seconds);
        $this->add('message_seconds_count.'.$short, 1);
    }

    /** @return array<string, int|float> compteurs indexés par clé logique */
    public function all(): array
    {
        $keys = $this->index();
        $values = [];
        foreach ($keys as $key) {
            $item = $this->cache->getItem(self::PREFIX.$key);
            $value = $item->get();
            if (is_int($value) || is_float($value)) {
                $values[$key] = $value;
            }
        }
        ksort($values);

        return $values;
    }

    private function add(string $key, int|float $delta): void
    {
        $item = $this->cache->getItem(self::PREFIX.$key);
        $current = $item->get();
        $item->set((is_int($current) || is_float($current) ? $current : 0) + $delta);
        $this->cache->save($item);

        $index = $this->index();
        if (!in_array($key, $index, true)) {
            $index[] = $key;
            $indexItem = $this->cache->getItem(self::INDEX_KEY);
            $indexItem->set($index);
            $this->cache->save($indexItem);
        }
    }

    /** @return list<string> */
    private function index(): array
    {
        $index = $this->cache->getItem(self::INDEX_KEY)->get();

        return is_array($index) ? array_values(array_filter($index, is_string(...))) : [];
    }
}
