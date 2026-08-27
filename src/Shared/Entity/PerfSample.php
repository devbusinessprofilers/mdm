<?php

declare(strict_types=1);

namespace App\Shared\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Point de série temporelle du monitoring (/admin/performance), inséré à la
 * minute par les workers eux-mêmes (le consumer cron ne passe que toutes les
 * 15 min). kind=worker : cumuls du processus (busy_ms, handled, mémoire…) ;
 * kind=queue : jauges des files messenger_messages (pending, delayed, âge).
 * Écriture en DBAL pur, rétention courte via app:performance:purge.
 */
#[ORM\Entity]
#[ORM\Table(name: 'perf_sample')]
#[ORM\Index(name: 'IDX_PERF_SAMPLE_TS', columns: ['sampled_at'])]
#[ORM\Index(name: 'IDX_PERF_SAMPLE_SUBJECT', columns: ['kind', 'subject', 'sampled_at'])]
final class PerfSample
{
    public const KIND_WORKER = 'worker';
    public const KIND_QUEUE = 'queue';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT, options: ['unsigned' => true])]
    private ?int $id = null;

    /**
     * @param array<string, mixed> $metrics
     */
    public function __construct(
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $sampledAt,
        #[ORM\Column(length: 16)]
        private string $kind,
        #[ORM\Column(length: 128)]
        private string $subject,
        #[ORM\Column(type: Types::JSON)]
        private array $metrics,
    ) {
    }

    public function id(): ?int
    {
        return $this->id;
    }
}
