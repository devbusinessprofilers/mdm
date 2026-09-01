<?php

declare(strict_types=1);

namespace App\Vision\Entity;

use App\Dam\Entity\MediaAsset;
use App\Pim\Entity\Fiche;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Shared\Entity\TimestampableTrait;
use App\Vision\Enum\EnhancementProvider;
use App\Vision\Enum\EnhancementStatus;
use App\Vision\Repository\ImageEnhancementRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

/**
 * Retouche IA d'une photo : le résultat proposé par le fournisseur reste une
 * candidate (bucket privé) tant qu'un humain ne l'a pas comparée à l'original
 * et acceptée. L'original n'est jamais modifié.
 */
#[ORM\Entity(repositoryClass: ImageEnhancementRepository::class)]
#[ORM\Table(name: 'vision_image_enhancement')]
#[ORM\Index(name: 'IDX_VISION_ENHANCEMENT_FICHE_CREATED', columns: ['fiche_id', 'created_at'])]
#[ORM\Index(name: 'IDX_VISION_ENHANCEMENT_STATUS', columns: ['status', 'updated_at'])]
#[ORM\Index(name: 'IDX_VISION_ENHANCEMENT_MEDIA', columns: ['media_asset_id'])]
#[ORM\Index(name: 'IDX_VISION_ENHANCEMENT_RESOURCE', columns: ['resource_id'])]
#[ORM\Index(name: 'IDX_VISION_ENHANCEMENT_PROVIDER_CREATED', columns: ['provider', 'created_at'])]
#[ORM\HasLifecycleCallbacks]
class ImageEnhancement
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    private Ulid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Fiche $fiche;

    #[ORM\ManyToOne(fetch: 'EAGER')]
    #[ORM\JoinColumn(name: 'media_asset_id', nullable: false, onDelete: 'RESTRICT')]
    private MediaAsset $media;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?RessourceLieu $resource;

    #[ORM\Column(length: 64)]
    private string $sourceChecksum;

    #[ORM\Column(type: Types::TEXT)]
    private string $prompt;

    #[ORM\Column(length: 32, enumType: EnhancementProvider::class, options: ['default' => 'openai'])]
    private EnhancementProvider $provider = EnhancementProvider::OpenAi;

    #[ORM\Column(length: 100)]
    private string $providerModel;

    #[ORM\Column(length: 32, enumType: EnhancementStatus::class)]
    private EnhancementStatus $status = EnhancementStatus::Queued;

    #[ORM\Column(length: 1024, nullable: true)]
    private ?string $resultStorageKey = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $resultChecksum = null;

    #[ORM\Column(type: Types::BIGINT, nullable: true)]
    private ?int $resultSizeBytes = null;

    #[ORM\Column(length: 180)]
    private string $createdBy;

    #[ORM\Column(type: Types::SMALLINT, options: ['unsigned' => true, 'default' => 0])]
    private int $attempts = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $finishedAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $errorMessage = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $rawResponse = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $decidedBy = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $decidedAt = null;

    public function __construct(Fiche $fiche, MediaAsset $media, ?RessourceLieu $resource, string $prompt, string $providerModel, string $actor, EnhancementProvider $provider = EnhancementProvider::OpenAi)
    {
        $this->id = new Ulid();
        $this->provider = $provider;
        $this->fiche = $fiche;
        $this->media = $media;
        $this->resource = $resource;
        $this->sourceChecksum = $media->checksum();
        $this->prompt = $prompt;
        $this->providerModel = mb_substr($providerModel, 0, 100);
        $this->createdBy = $actor;
        $this->initializeTimestamps();
    }

    public function id(): string { return (string) $this->id; }
    public function fiche(): Fiche { return $this->fiche; }
    public function media(): MediaAsset { return $this->media; }
    public function resource(): ?RessourceLieu { return $this->resource; }
    public function sourceChecksum(): string { return $this->sourceChecksum; }
    public function prompt(): string { return $this->prompt; }
    public function provider(): EnhancementProvider { return $this->provider; }
    public function providerModel(): string { return $this->providerModel; }
    public function status(): EnhancementStatus { return $this->status; }
    public function resultStorageKey(): ?string { return $this->resultStorageKey; }
    public function resultChecksum(): ?string { return $this->resultChecksum; }
    public function resultSizeBytes(): ?int { return $this->resultSizeBytes; }
    public function createdBy(): string { return $this->createdBy; }
    public function attempts(): int { return $this->attempts; }
    public function startedAt(): ?\DateTimeImmutable { return $this->startedAt; }
    public function finishedAt(): ?\DateTimeImmutable { return $this->finishedAt; }
    public function errorMessage(): ?string { return $this->errorMessage; }
    /** @return array<string, mixed>|null */ public function rawResponse(): ?array { return $this->rawResponse; }
    public function decidedBy(): ?string { return $this->decidedBy; }
    public function decidedAt(): ?\DateTimeImmutable { return $this->decidedAt; }

    public function start(): void
    {
        if (!in_array($this->status, [EnhancementStatus::Queued, EnhancementStatus::Failed], true)) {
            return;
        }
        $this->status = EnhancementStatus::Processing;
        $this->startedAt = new \DateTimeImmutable();
        $this->finishedAt = null;
        $this->errorMessage = null;
        ++$this->attempts;
        $this->touch();
    }

    /** @param array<string, mixed>|null $raw métadonnées du fournisseur, jamais l'image encodée */
    public function complete(string $storageKey, string $checksum, int $sizeBytes, ?array $raw): void
    {
        $this->resultStorageKey = $storageKey;
        $this->resultChecksum = $checksum;
        $this->resultSizeBytes = $sizeBytes;
        $this->rawResponse = $raw;
        $this->status = EnhancementStatus::Ready;
        $this->finishedAt = new \DateTimeImmutable();
        $this->errorMessage = null;
        $this->touch();
    }

    public function fail(string $message): void
    {
        $this->status = EnhancementStatus::Failed;
        $this->errorMessage = mb_substr(trim($message), 0, 4000);
        $this->finishedAt = new \DateTimeImmutable();
        $this->touch();
    }

    public function requeue(): void
    {
        if (EnhancementStatus::Failed !== $this->status) {
            throw new \DomainException('Seule une retouche en échec peut être relancée.');
        }
        $this->status = EnhancementStatus::Queued;
        $this->errorMessage = null;
        $this->touch();
    }

    public function accept(string $actor): void
    {
        if (EnhancementStatus::Ready !== $this->status) {
            throw new \DomainException('Seule une retouche prête peut être acceptée.');
        }
        $this->status = EnhancementStatus::Accepted;
        $this->decidedBy = $actor;
        $this->decidedAt = new \DateTimeImmutable();
        $this->touch();
    }

    public function reject(string $actor): void
    {
        if (EnhancementStatus::Ready !== $this->status) {
            throw new \DomainException('Seule une retouche prête peut être rejetée.');
        }
        $this->status = EnhancementStatus::Rejected;
        $this->decidedBy = $actor;
        $this->decidedAt = new \DateTimeImmutable();
        $this->touch();
    }
}
