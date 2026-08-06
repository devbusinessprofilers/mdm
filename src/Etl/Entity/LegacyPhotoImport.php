<?php

declare(strict_types=1);

namespace App\Etl\Entity;

use App\Etl\Enum\LegacyPhotoStatus;
use App\Etl\Repository\LegacyPhotoImportRepository;
use App\Shared\Entity\TimestampableTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** Suivi unitaire de l'import d'une photo legacy (reprise par lots). */
#[ORM\Entity(repositoryClass: LegacyPhotoImportRepository::class)]
#[ORM\Table(name: 'etl_legacy_photo')]
#[ORM\UniqueConstraint(name: 'UNIQ_ETL_LEGACY_PHOTO_PATH', columns: ['syspad_id', 'legacy_path'])]
#[ORM\Index(name: 'IDX_ETL_LEGACY_PHOTO_STATUS', columns: ['status', 'syspad_id'])]
#[ORM\HasLifecycleCallbacks]
class LegacyPhotoImport
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\Column(options: ['unsigned' => true])]
    private int $syspadId;

    #[ORM\Column(length: 500)]
    private string $legacyPath;

    #[ORM\Column(length: 32)]
    private string $category;

    #[ORM\Column(length: 32)]
    private string $usageCode;

    #[ORM\Column(type: Types::SMALLINT, options: ['unsigned' => true])]
    private int $position;

    #[ORM\Column(length: 16, enumType: LegacyPhotoStatus::class)]
    private LegacyPhotoStatus $status;

    #[ORM\Column(length: 26, nullable: true)]
    private ?string $mediaAssetId = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(type: Types::SMALLINT, options: ['unsigned' => true, 'default' => 0])]
    private int $attempts = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $processedAt = null;

    public function __construct(int $syspadId, string $legacyPath, string $category, string $usageCode, int $position, LegacyPhotoStatus $status = LegacyPhotoStatus::Pending)
    {
        $this->syspadId = $syspadId;
        $this->legacyPath = mb_substr($legacyPath, 0, 500);
        $this->category = mb_substr($category, 0, 32);
        $this->usageCode = mb_substr($usageCode, 0, 32);
        $this->position = $position;
        $this->status = $status;
        $this->initializeTimestamps();
    }

    public function id(): ?int { return $this->id; }
    public function syspadId(): int { return $this->syspadId; }
    public function legacyPath(): string { return $this->legacyPath; }
    public function category(): string { return $this->category; }
    public function usageCode(): string { return $this->usageCode; }
    public function position(): int { return $this->position; }
    public function status(): LegacyPhotoStatus { return $this->status; }
    public function mediaAssetId(): ?string { return $this->mediaAssetId; }
    public function errorMessage(): ?string { return $this->errorMessage; }
    public function attempts(): int { return $this->attempts; }
    public function processedAt(): ?\DateTimeImmutable { return $this->processedAt; }

    public function markDone(string $mediaAssetId): void
    {
        $this->status = LegacyPhotoStatus::Done;
        $this->mediaAssetId = $mediaAssetId;
        $this->errorMessage = null;
        $this->processedAt = new \DateTimeImmutable();
        $this->touch();
    }

    public function markFailed(LegacyPhotoStatus $status, string $message): void
    {
        if (LegacyPhotoStatus::Done === $status || LegacyPhotoStatus::Pending === $status) {
            throw new \DomainException('Statut d’échec photo invalide.');
        }
        $this->status = $status;
        ++$this->attempts;
        $this->errorMessage = mb_substr($message, 0, 2000);
        $this->processedAt = new \DateTimeImmutable();
        $this->touch();
    }

    public function retry(): void
    {
        $this->status = LegacyPhotoStatus::Pending;
        $this->touch();
    }
}
