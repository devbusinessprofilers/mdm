<?php

declare(strict_types=1);

namespace App\Dam\Entity;

use App\Dam\Repository\MediaAssetRepository;
use App\Dam\Enum\MediaStatus;
use App\Shared\Entity\TimestampableTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: MediaAssetRepository::class)]
#[ORM\Table(name: 'dam_media_asset')]
#[ORM\Index(name: 'IDX_DAM_MEDIA_CHECKSUM', columns: ['checksum'])]
#[ORM\Index(name: 'IDX_DAM_MEDIA_STATUS_CREATED', columns: ['status', 'created_at'])]
#[ORM\HasLifecycleCallbacks]
final class MediaAsset
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    private Ulid $id;

    #[ORM\Column(length: 1024, unique: true)]
    private string $originalStorageKey;

    #[ORM\Column(length: 255)]
    private string $originalFilename;

    #[ORM\Column(length: 100)]
    private string $mimeType;

    #[ORM\Column(type: 'bigint')]
    private int $sizeBytes;

    #[ORM\Column(length: 64)]
    private string $checksum;

    #[ORM\Column(length: 32, enumType: MediaStatus::class)]
    private MediaStatus $status = MediaStatus::Uploaded;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $processedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    /** @var Collection<int, MediaRendition> */
    #[ORM\OneToMany(mappedBy: 'media', targetEntity: MediaRendition::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $renditions;

    public function __construct(
        Ulid $id,
        string $originalStorageKey,
        string $originalFilename,
        string $mimeType,
        int $sizeBytes,
        string $checksum,
    ) {
        $this->id = $id;
        $this->originalStorageKey = $originalStorageKey;
        $this->originalFilename = $originalFilename;
        $this->mimeType = $mimeType;
        $this->sizeBytes = $sizeBytes;
        $this->checksum = $checksum;
        $this->renditions = new ArrayCollection();
        $this->initializeTimestamps();
    }

    public function id(): string { return (string) $this->id; }
    public function originalStorageKey(): string { return $this->originalStorageKey; }
    public function originalFilename(): string { return $this->originalFilename; }
    public function mimeType(): string { return $this->mimeType; }
    public function sizeBytes(): int { return $this->sizeBytes; }
    public function checksum(): string { return $this->checksum; }
    public function status(): MediaStatus { return $this->status; }
    public function errorMessage(): ?string { return $this->errorMessage; }
    public function processedAt(): ?\DateTimeImmutable { return $this->processedAt; }
    public function deletedAt(): ?\DateTimeImmutable { return $this->deletedAt; }

    /** @return Collection<int, MediaRendition> */
    public function renditions(): Collection { return $this->renditions; }

    public function rendition(string $name): ?MediaRendition
    {
        foreach ($this->renditions as $rendition) {
            if ($rendition->name() === $name) {
                return $rendition;
            }
        }

        return null;
    }

    public function addRendition(MediaRendition $rendition): void
    {
        if (!$this->renditions->contains($rendition)) {
            $this->renditions->add($rendition);
        }
    }

    public function markProcessing(): void
    {
        $this->status = MediaStatus::Processing;
        $this->errorMessage = null;
        $this->touch();
    }

    public function markProcessed(): void
    {
        $this->status = MediaStatus::Processed;
        $this->errorMessage = null;
        $this->processedAt = new \DateTimeImmutable();
        $this->touch();
    }

    public function markFailed(string $message): void
    {
        $this->status = MediaStatus::Failed;
        $this->errorMessage = mb_substr($message, 0, 2000);
        $this->touch();
    }

    public function markDeleting(): void
    {
        $this->status = MediaStatus::Deleting;
        $this->touch();
    }

    public function markDeleted(): void
    {
        $this->status = MediaStatus::Deleted;
        $this->deletedAt = new \DateTimeImmutable();
        $this->touch();
    }
}
