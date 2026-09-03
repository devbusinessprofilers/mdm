<?php

declare(strict_types=1);

namespace App\Pim\Entity;

use App\Pim\Enum\DuplicateReviewStatus;
use App\Pim\Enum\TextDuplicateKind;
use App\Pim\Repository\TextDuplicateAlertRepository;
use App\Shared\Entity\TimestampableTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

/**
 * Signalement d'un champ de texte en doublon d'un autre. Miroir strict de
 * App\Dam\Entity\MediaDuplicateAlert, transposé du média au champ de fiche.
 */
#[ORM\Entity(repositoryClass: TextDuplicateAlertRepository::class)]
#[ORM\Table(name: 'pim_text_duplicate_alert')]
#[ORM\UniqueConstraint(name: 'UNIQ_PIM_TEXT_DUPLICATE_FP', columns: ['fingerprint_id'])]
#[ORM\Index(name: 'IDX_PIM_TEXT_DUPLICATE_STATUS_CREATED', columns: ['status', 'created_at'])]
#[ORM\Index(name: 'IDX_PIM_TEXT_DUPLICATE_FICHE_TYPE', columns: ['fiche_type', 'status'])]
#[ORM\Index(name: 'IDX_PIM_TEXT_DUPLICATE_REFERENCE', columns: ['duplicate_of_id'])]
#[ORM\HasLifecycleCallbacks]
class TextDuplicateAlert
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    private Ulid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'fingerprint_id', nullable: false, onDelete: 'CASCADE')]
    private TextFingerprint $fingerprint;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'duplicate_of_id', nullable: false, onDelete: 'CASCADE')]
    private TextFingerprint $duplicateOf;

    #[ORM\Column(length: 26)]
    private string $ficheId;

    #[ORM\Column(length: 32)]
    private string $ficheType;

    #[ORM\Column(length: 191)]
    private string $fieldPath;

    #[ORM\Column(length: 16, enumType: TextDuplicateKind::class)]
    private TextDuplicateKind $kind;

    #[ORM\Column(nullable: true)]
    private ?int $distance;

    #[ORM\Column(length: 16, enumType: DuplicateReviewStatus::class)]
    private DuplicateReviewStatus $status = DuplicateReviewStatus::Pending;

    #[ORM\Column(length: 26, nullable: true)]
    private ?string $reviewedBy = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $reviewedAt = null;

    public function __construct(TextFingerprint $fingerprint, TextFingerprint $duplicateOf, TextDuplicateKind $kind, ?int $distance)
    {
        $this->id = new Ulid();
        $this->fingerprint = $fingerprint;
        $this->duplicateOf = $duplicateOf;
        $this->ficheId = $fingerprint->ficheId();
        $this->ficheType = $fingerprint->ficheType();
        $this->fieldPath = $fingerprint->fieldPath();
        $this->kind = $kind;
        $this->distance = $distance;
        $this->initializeTimestamps();
    }

    public function id(): string
    {
        return (string) $this->id;
    }

    public function fingerprint(): TextFingerprint
    {
        return $this->fingerprint;
    }

    public function duplicateOf(): TextFingerprint
    {
        return $this->duplicateOf;
    }

    public function ficheId(): string
    {
        return $this->ficheId;
    }

    public function ficheType(): string
    {
        return $this->ficheType;
    }

    public function fieldPath(): string
    {
        return $this->fieldPath;
    }

    public function kind(): TextDuplicateKind
    {
        return $this->kind;
    }

    public function distance(): ?int
    {
        return $this->distance;
    }

    public function status(): DuplicateReviewStatus
    {
        return $this->status;
    }

    public function reviewedBy(): ?string
    {
        return $this->reviewedBy;
    }

    public function reviewedAt(): ?\DateTimeImmutable
    {
        return $this->reviewedAt;
    }

    public function refresh(TextFingerprint $duplicateOf, TextDuplicateKind $kind, ?int $distance): void
    {
        if ($this->duplicateOf === $duplicateOf && $this->kind === $kind && $this->distance === $distance) {
            return;
        }

        $this->duplicateOf = $duplicateOf;
        $this->kind = $kind;
        $this->distance = $distance;
        $this->status = DuplicateReviewStatus::Pending;
        $this->reviewedBy = null;
        $this->reviewedAt = null;
        $this->touch();
    }

    /** Doublon acté comme légitime : conservé mais sorti de la file. */
    public function accept(string $actor): void
    {
        $this->status = DuplicateReviewStatus::Accepted;
        $this->reviewedBy = $actor;
        $this->reviewedAt = new \DateTimeImmutable();
        $this->touch();
    }

    /** Faux positif ou doublon disparu : sorti de la file. */
    public function resolve(string $actor): void
    {
        $this->status = DuplicateReviewStatus::Resolved;
        $this->reviewedBy = $actor;
        $this->reviewedAt = new \DateTimeImmutable();
        $this->touch();
    }
}
