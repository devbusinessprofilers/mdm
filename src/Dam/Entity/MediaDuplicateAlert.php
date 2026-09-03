<?php

declare(strict_types=1);

namespace App\Dam\Entity;

use App\Dam\Enum\DuplicateKind;
use App\Dam\Enum\DuplicateReviewStatus;
use App\Dam\Repository\MediaDuplicateAlertRepository;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Shared\Entity\TimestampableTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: MediaDuplicateAlertRepository::class)]
#[ORM\Table(name: 'dam_media_duplicate_alert')]
#[ORM\UniqueConstraint(name: 'UNIQ_DAM_DUPLICATE_MEDIA', columns: ['media_id'])]
#[ORM\Index(name: 'IDX_DAM_DUPLICATE_STATUS_CREATED', columns: ['status', 'created_at'])]
#[ORM\Index(name: 'IDX_DAM_DUPLICATE_FICHE_TYPE', columns: ['fiche_type', 'status'])]
#[ORM\Index(name: 'IDX_DAM_DUPLICATE_REFERENCE', columns: ['duplicate_of_id'])]
#[ORM\HasLifecycleCallbacks]
class MediaDuplicateAlert
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    private Ulid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private MediaAsset $media;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'duplicate_of_id', nullable: false, onDelete: 'CASCADE')]
    private MediaAsset $duplicateOf;

    #[ORM\Column(length: 26)]
    private string $resourceId;

    #[ORM\Column(length: 26)]
    private string $ficheId;

    #[ORM\Column(length: 32)]
    private string $ficheType;

    #[ORM\Column(length: 16, enumType: DuplicateKind::class)]
    private DuplicateKind $kind;

    #[ORM\Column(nullable: true)]
    private ?int $distance;

    #[ORM\Column(length: 16, enumType: DuplicateReviewStatus::class)]
    private DuplicateReviewStatus $status = DuplicateReviewStatus::Pending;

    #[ORM\Column(length: 26, nullable: true)]
    private ?string $reviewedBy = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $reviewedAt = null;

    public function __construct(MediaAsset $media, MediaAsset $duplicateOf, RessourceLieu $resource, DuplicateKind $kind, ?int $distance)
    {
        $this->id = new Ulid();
        $this->media = $media;
        $this->duplicateOf = $duplicateOf;
        $this->resourceId = $resource->id();
        $this->ficheId = $resource->fiche()?->idString() ?? throw new \DomainException('La ressource DAM doit être rattachée à une fiche.');
        $this->ficheType = $resource->fiche()->type()->value;
        $this->kind = $kind;
        $this->distance = $distance;
        $this->initializeTimestamps();
    }

    public function id(): string
    {
        return (string) $this->id;
    }

    public function media(): MediaAsset
    {
        return $this->media;
    }

    public function duplicateOf(): MediaAsset
    {
        return $this->duplicateOf;
    }

    public function resourceId(): string
    {
        return $this->resourceId;
    }

    public function ficheId(): string
    {
        return $this->ficheId;
    }

    public function ficheType(): string
    {
        return $this->ficheType;
    }

    public function kind(): DuplicateKind
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

    public function refresh(MediaAsset $duplicateOf, DuplicateKind $kind, ?int $distance): void
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

    public function accept(string $actor): void
    {
        $this->status = DuplicateReviewStatus::Accepted;
        $this->reviewedBy = $actor;
        $this->reviewedAt = new \DateTimeImmutable();
        $this->touch();
    }

    public function resolve(string $actor): void
    {
        $this->status = DuplicateReviewStatus::Resolved;
        $this->reviewedBy = $actor;
        $this->reviewedAt = new \DateTimeImmutable();
        $this->touch();
    }
}
