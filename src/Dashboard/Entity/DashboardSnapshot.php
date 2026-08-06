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
final class DashboardSnapshot
{
    public const int SCHEMA_VERSION = 1;
    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    private Ulid $id;
    #[ORM\Column(type: Types::SMALLINT, options: ['unsigned' => true])]
    private int $schemaVersion;
    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON)]
    private array $payload;
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $computedAt;
    #[ORM\Column(type: Types::INTEGER, options: ['unsigned' => true])]
    private int $durationMs;

    /** @param array<string, mixed> $payload */
    public function __construct(array $payload, int $durationMs)
    {
        $this->id = new Ulid();
        $this->schemaVersion = self::SCHEMA_VERSION;
        $this->payload = $payload;
        $this->computedAt = new \DateTimeImmutable();
        $this->durationMs = max(0, $durationMs);
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
