<?php

declare(strict_types=1);

namespace App\Ocr\Entity;

use App\Dam\Entity\MediaAsset;
use App\Ocr\Enum\ExtractionStatus;
use App\Ocr\Repository\DocumentExtractionRepository;
use App\Pim\Entity\Fiche;
use App\Shared\Entity\TimestampableTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: DocumentExtractionRepository::class)]
#[ORM\Table(name: 'ocr_document_extraction')]
#[ORM\Index(name: 'IDX_OCR_EXTRACTION_FICHE_CREATED', columns: ['fiche_id', 'created_at'])]
#[ORM\Index(name: 'IDX_OCR_EXTRACTION_STATUS', columns: ['status', 'updated_at'])]
#[ORM\Index(name: 'IDX_OCR_EXTRACTION_MEDIA', columns: ['media_asset_id'])]
#[ORM\Index(name: 'IDX_OCR_EXTRACTION_RETRY', columns: ['retry_of_id'])]
#[ORM\HasLifecycleCallbacks]
class DocumentExtraction
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
    private MediaAsset $document;

    #[ORM\ManyToOne(targetEntity: self::class, fetch: 'EAGER')]
    #[ORM\JoinColumn(name: 'retry_of_id', nullable: true, onDelete: 'SET NULL')]
    private ?self $retryOf;

    #[ORM\Column(length: 64)]
    private string $documentChecksum;

    #[ORM\Column(length: 64)]
    private string $documentCategory;

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON)]
    private array $schemaSnapshot;

    #[ORM\Column(length: 64)]
    private string $schemaFingerprint;

    #[ORM\Column(length: 32, enumType: ExtractionStatus::class)]
    private ExtractionStatus $status = ExtractionStatus::Queued;

    #[ORM\Column(length: 180)]
    private string $createdBy;

    #[ORM\Column(type: Types::SMALLINT, options: ['unsigned' => true, 'default' => 0])]
    private int $attempts = 0;

    #[ORM\Column(type: Types::SMALLINT, nullable: true, options: ['unsigned' => true])]
    private ?int $pageCount = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $finishedAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $errorMessage = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $rawResponse = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $providerAgent = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $providerModel = null;

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $temporaryBoxFileIds = [];

    /** @var Collection<int, OcrSuggestion> */
    #[ORM\OneToMany(mappedBy: 'extraction', targetEntity: OcrSuggestion::class, cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['fieldPath' => 'ASC'])]
    private Collection $suggestions;

    /** @param array<string, mixed> $schemaSnapshot */
    public function __construct(Fiche $fiche, MediaAsset $document, string $category, array $schemaSnapshot, string $actor, ?self $retryOf = null)
    {
        $this->id = new Ulid();
        $this->fiche = $fiche;
        $this->document = $document;
        $this->documentChecksum = $document->checksum();
        $this->documentCategory = $category;
        $this->schemaSnapshot = $schemaSnapshot;
        $encoded = json_encode($schemaSnapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->schemaFingerprint = hash('sha256', $encoded);
        $this->createdBy = $actor;
        $this->retryOf = $retryOf;
        $this->suggestions = new ArrayCollection();
        $this->initializeTimestamps();
    }

    public function id(): string { return (string) $this->id; }
    public function fiche(): Fiche { return $this->fiche; }
    public function document(): MediaAsset { return $this->document; }
    public function retryOf(): ?self { return $this->retryOf; }
    public function documentChecksum(): string { return $this->documentChecksum; }
    public function documentCategory(): string { return $this->documentCategory; }
    /** @return array<string, mixed> */ public function schemaSnapshot(): array { return $this->schemaSnapshot; }
    public function schemaFingerprint(): string { return $this->schemaFingerprint; }
    public function status(): ExtractionStatus { return $this->status; }
    public function createdBy(): string { return $this->createdBy; }
    public function attempts(): int { return $this->attempts; }
    public function pageCount(): ?int { return $this->pageCount; }
    public function startedAt(): ?\DateTimeImmutable { return $this->startedAt; }
    public function finishedAt(): ?\DateTimeImmutable { return $this->finishedAt; }
    public function errorMessage(): ?string { return $this->errorMessage; }
    /** @return array<string, mixed>|null */ public function rawResponse(): ?array { return $this->rawResponse; }
    public function providerAgent(): ?string { return $this->providerAgent; }
    public function providerModel(): ?string { return $this->providerModel; }
    /** @return list<string> */ public function temporaryBoxFileIds(): array { return $this->temporaryBoxFileIds; }
    /** @return Collection<int, OcrSuggestion> */ public function suggestions(): Collection { return $this->suggestions; }
    public function suggestionCount(): int { return $this->suggestions->count(); }

    public function start(int $pages): void
    {
        if (!in_array($this->status, [ExtractionStatus::Queued, ExtractionStatus::Failed], true)) {
            return;
        }
        $this->status = ExtractionStatus::Processing;
        $this->pageCount = $pages;
        $this->startedAt = new \DateTimeImmutable();
        $this->finishedAt = null;
        $this->errorMessage = null;
        ++$this->attempts;
        $this->touch();
    }

    public function rememberTemporaryBoxFile(string $fileId): void
    {
        if (!in_array($fileId, $this->temporaryBoxFileIds, true)) { $this->temporaryBoxFileIds[] = $fileId; }
        $this->touch();
    }

    public function forgetTemporaryBoxFile(string $fileId): void
    {
        $this->temporaryBoxFileIds = array_values(array_filter($this->temporaryBoxFileIds, static fn (string $id): bool => $id !== $fileId));
        $this->touch();
    }

    /** @param array<string, mixed> $raw */
    public function complete(array $raw, ?string $agent, ?string $model): void
    {
        $this->rawResponse = $raw;
        $this->providerAgent = self::short($agent);
        $this->providerModel = self::short($model);
        $this->status = ExtractionStatus::Ready;
        $this->finishedAt = new \DateTimeImmutable();
        $this->errorMessage = null;
        $this->touch();
    }

    public function fail(string $message): void
    {
        $this->status = ExtractionStatus::Failed;
        $this->errorMessage = mb_substr(trim($message), 0, 4000);
        $this->finishedAt = new \DateTimeImmutable();
        $this->touch();
    }

    public function recordTechnicalAttempt(): void
    {
        ++$this->attempts;
        $this->touch();
    }

    public function addSuggestion(OcrSuggestion $suggestion): void
    {
        if (!$this->suggestions->contains($suggestion)) { $this->suggestions->add($suggestion); }
    }

    public function refreshReviewStatus(): void
    {
        $pending = $decided = 0;
        foreach ($this->suggestions as $suggestion) {
            if ($suggestion->isPending()) { ++$pending; } else { ++$decided; }
        }
        $this->status = 0 === $pending ? ExtractionStatus::Reviewed : (0 < $decided ? ExtractionStatus::PartiallyReviewed : ExtractionStatus::Ready);
        $this->touch();
    }

    private static function short(?string $value): ?string
    {
        $value = null === $value ? null : trim($value);
        return null === $value || '' === $value ? null : mb_substr($value, 0, 100);
    }
}
