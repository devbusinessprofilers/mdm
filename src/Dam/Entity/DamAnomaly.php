<?php

declare(strict_types=1);

namespace App\Dam\Entity;

use App\Dam\Enum\DamAnomalyType;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

/**
 * Incohérence détectée par le scan périodique du DAM. Une ligne par sujet et
 * par type ; le scan rouvre ou résout les lignes existantes plutôt que d'en
 * créer de nouvelles, l'historique de résolution reste donc consultable.
 */
#[ORM\Entity]
#[ORM\Table(name: 'dam_anomaly')]
#[ORM\UniqueConstraint(name: 'UNIQ_DAM_ANOMALY_SUBJECT', columns: ['type', 'subject_id'])]
#[ORM\Index(name: 'IDX_DAM_ANOMALY_OPEN', columns: ['type', 'resolved_at'])]
class DamAnomaly
{
    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    private Ulid $id;
    #[ORM\Column(length: 32, enumType: DamAnomalyType::class)]
    private DamAnomalyType $type;
    #[ORM\Column(length: 255)]
    private string $subjectId;
    #[ORM\Column]
    private \DateTimeImmutable $detectedAt;
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $resolvedAt = null;

    public function __construct(DamAnomalyType $type, string $subjectId)
    {
        $this->id = new Ulid();
        $this->type = $type;
        $this->subjectId = $subjectId;
        $this->detectedAt = new \DateTimeImmutable();
    }

    public function id(): string
    {
        return (string) $this->id;
    }

    public function type(): DamAnomalyType
    {
        return $this->type;
    }

    public function subjectId(): string
    {
        return $this->subjectId;
    }

    public function detectedAt(): \DateTimeImmutable
    {
        return $this->detectedAt;
    }

    public function resolvedAt(): ?\DateTimeImmutable
    {
        return $this->resolvedAt;
    }

    public function isOpen(): bool
    {
        return null === $this->resolvedAt;
    }

    public function resolve(): void
    {
        $this->resolvedAt ??= new \DateTimeImmutable();
    }

    public function reopen(): void
    {
        if (null !== $this->resolvedAt) {
            $this->resolvedAt = null;
            $this->detectedAt = new \DateTimeImmutable();
        }
    }
}
