<?php

declare(strict_types=1);

namespace App\Vision\Entity;

use App\Dam\Entity\MediaAsset;
use App\Pim\Entity\Fiche;
use App\Pim\Entity\Lieu\RessourceLieu;
use App\Shared\Entity\TimestampableTrait;
use App\Vision\Enum\RecognitionStatus;
use App\Vision\Repository\ImageRecognitionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

/**
 * Reconnaissance IA d'une photo : ce que le fournisseur déduit de l'image
 * (légende, mots-clés, contexte) reste en suggestions révisables — rien n'est
 * écrit sur la ressource sans décision humaine.
 */
#[ORM\Entity(repositoryClass: ImageRecognitionRepository::class)]
#[ORM\Table(name: 'vision_image_recognition')]
#[ORM\Index(name: 'IDX_VISION_RECOGNITION_FICHE_CREATED', columns: ['fiche_id', 'created_at'])]
#[ORM\Index(name: 'IDX_VISION_RECOGNITION_STATUS', columns: ['status', 'updated_at'])]
#[ORM\Index(name: 'IDX_VISION_RECOGNITION_MEDIA', columns: ['media_asset_id'])]
#[ORM\Index(name: 'IDX_VISION_RECOGNITION_RESOURCE', columns: ['resource_id'])]
#[ORM\HasLifecycleCallbacks]
class ImageRecognition
{
    use TimestampableTrait;

    /** Auteur conventionnel des recos déclenchées par l'import d'un média. */
    public const CREATED_BY_AUTO = 'auto';

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
    #[ORM\JoinColumn(name: 'resource_id', nullable: false, onDelete: 'CASCADE')]
    private RessourceLieu $resource;

    #[ORM\Column(length: 64)]
    private string $sourceChecksum;

    #[ORM\Column(type: Types::TEXT)]
    private string $prompt;

    #[ORM\Column(length: 100)]
    private string $providerModel;

    #[ORM\Column(length: 32, enumType: RecognitionStatus::class)]
    private RecognitionStatus $status = RecognitionStatus::Queued;

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

    /** @var Collection<int, ImageRecognitionSuggestion> */
    #[ORM\OneToMany(mappedBy: 'recognition', targetEntity: ImageRecognitionSuggestion::class, cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['fieldPath' => 'ASC'])]
    private Collection $suggestions;

    public function __construct(Fiche $fiche, MediaAsset $media, RessourceLieu $resource, string $prompt, string $providerModel, string $actor)
    {
        $this->id = new Ulid();
        $this->fiche = $fiche;
        $this->media = $media;
        $this->resource = $resource;
        $this->sourceChecksum = $media->checksum();
        $this->prompt = $prompt;
        $this->providerModel = mb_substr($providerModel, 0, 100);
        $this->createdBy = $actor;
        $this->suggestions = new ArrayCollection();
        $this->initializeTimestamps();
    }

    public function id(): string { return (string) $this->id; }
    public function fiche(): Fiche { return $this->fiche; }
    public function media(): MediaAsset { return $this->media; }
    public function resource(): RessourceLieu { return $this->resource; }
    public function sourceChecksum(): string { return $this->sourceChecksum; }
    public function prompt(): string { return $this->prompt; }
    public function providerModel(): string { return $this->providerModel; }
    public function status(): RecognitionStatus { return $this->status; }
    public function createdBy(): string { return $this->createdBy; }
    public function attempts(): int { return $this->attempts; }
    public function startedAt(): ?\DateTimeImmutable { return $this->startedAt; }
    public function finishedAt(): ?\DateTimeImmutable { return $this->finishedAt; }
    public function errorMessage(): ?string { return $this->errorMessage; }
    /** @return array<string, mixed>|null */ public function rawResponse(): ?array { return $this->rawResponse; }
    /** @return Collection<int, ImageRecognitionSuggestion> */ public function suggestions(): Collection { return $this->suggestions; }

    public function start(): void
    {
        if (!in_array($this->status, [RecognitionStatus::Queued, RecognitionStatus::Failed], true)) {
            return;
        }
        $this->status = RecognitionStatus::Processing;
        $this->startedAt = new \DateTimeImmutable();
        $this->finishedAt = null;
        $this->errorMessage = null;
        ++$this->attempts;
        $this->touch();
    }

    /** @param array<string, mixed>|null $raw */
    public function complete(?array $raw): void
    {
        $this->rawResponse = $raw;
        $this->status = RecognitionStatus::Ready;
        $this->finishedAt = new \DateTimeImmutable();
        $this->errorMessage = null;
        $this->touch();
    }

    public function fail(string $message): void
    {
        $this->status = RecognitionStatus::Failed;
        $this->errorMessage = mb_substr(trim($message), 0, 4000);
        $this->finishedAt = new \DateTimeImmutable();
        $this->touch();
    }

    public function requeue(): void
    {
        if (RecognitionStatus::Failed !== $this->status) {
            throw new \DomainException('Seule une reconnaissance en échec peut être relancée.');
        }
        $this->status = RecognitionStatus::Queued;
        $this->errorMessage = null;
        $this->touch();
    }

    public function addSuggestion(ImageRecognitionSuggestion $suggestion): void
    {
        if (!$this->suggestions->contains($suggestion)) {
            $this->suggestions->add($suggestion);
        }
    }

    public function refreshReviewStatus(): void
    {
        $pending = $decided = 0;
        foreach ($this->suggestions as $suggestion) {
            if ($suggestion->isPending()) { ++$pending; } else { ++$decided; }
        }
        $this->status = 0 === $pending ? RecognitionStatus::Reviewed : (0 < $decided ? RecognitionStatus::PartiallyReviewed : RecognitionStatus::Ready);
        $this->touch();
    }
}
