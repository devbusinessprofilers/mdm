<?php

declare(strict_types=1);

namespace App\Dashboard\Entity;

use App\Dashboard\Repository\DashboardSnapshotRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: DashboardSnapshotRepository::class)]
#[ORM\Table(name: 'dashboard_snapshot')]
#[
    ORM\Index(
        name: 'IDX_DASHBOARD_SNAPSHOT_COMPUTED',
        columns: ['computed_at', 'id'],
    ),
]
#[
    ORM\Index(
        name: 'IDX_DASHBOARD_SNAPSHOT_KIND',
        columns: ['kind', 'computed_at', 'id'],
    ),
]
final class DashboardSnapshot
{
    public const int SCHEMA_VERSION = 1;
    public const string KIND_STATS = 'stats';
    public const string KIND_FIELD_FILL = 'field_fill';
    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    private Ulid $id;
    #[ORM\Column(type: Types::SMALLINT, options: ['unsigned' => true])]
    private int $schemaVersion;
    #[ORM\Column(length: 32, options: ['default' => self::KIND_STATS])]
    private string $kind;
    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON)]
    private array $payload;
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $computedAt;
    #[ORM\Column(type: Types::INTEGER, options: ['unsigned' => true])]
    private int $durationMs;

    /** @param array<string, mixed> $payload */
    public function __construct(array $payload, int $durationMs, string $kind = self::KIND_STATS)
    {
        $this->id = new Ulid();
        $this->schemaVersion = self::SCHEMA_VERSION;
        $this->kind = $kind;
        $this->payload = $payload;
        $this->computedAt = new \DateTimeImmutable();
        $this->durationMs = max(0, $durationMs);
    }

    public function kind(): string
    {
        return $this->kind;
    }

    public function id(): string
    {
        return (string) $this->id;
    }

    public function schemaVersion(): int
    {
        return $this->schemaVersion;
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return $this->payload;
    }

    public function computedAt(): \DateTimeImmutable
    {
        return $this->computedAt;
    }

    public function durationMs(): int
    {
        return $this->durationMs;
    }
}
