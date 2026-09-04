<?php

declare(strict_types=1);

namespace App\Ocr\Entity;

use App\Shared\Enum\DecisionStatus;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
#[ORM\Table(name: 'ocr_suggestion')]
#[ORM\UniqueConstraint(name: 'UNIQ_OCR_SUGGESTION_PATH', columns: ['extraction_id', 'field_path'])]
final class OcrSuggestion
{
    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    private Ulid $id;
    #[ORM\ManyToOne(inversedBy: 'suggestions', fetch: 'EAGER')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private DocumentExtraction $extraction;
    #[ORM\Column(name: 'field_path', length: 255)]
    private string $fieldPath;
    #[ORM\Column(length: 255)]
    private string $label;
    #[ORM\Column(length: 32)]
    private string $valueType;
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private mixed $rawValue;
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private mixed $correctedValue;
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private mixed $observedValue;
    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 4, nullable: true)]
    private ?string $confidence;
    /** @var list<int> */
    #[ORM\Column(type: Types::JSON)]
    private array $pageReferences;
    #[ORM\Column(length: 16, enumType: DecisionStatus::class)]
    private DecisionStatus $status = DecisionStatus::Pending;
    #[ORM\Column(length: 180, nullable: true)]
    private ?string $decidedBy = null;
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $decidedAt = null;

    /** @param list<int> $pageReferences */
    public function __construct(DocumentExtraction $extraction, string $path, string $label, string $type, mixed $rawValue, mixed $observedValue, ?float $confidence, array $pageReferences)
    {
        $this->id = new Ulid();
        $this->extraction = $extraction;
        $this->fieldPath = $path;
        $this->label = $label;
        $this->valueType = $type;
        $this->rawValue = $rawValue;
        $this->correctedValue = $rawValue;
        $this->observedValue = $observedValue;
        $this->confidence = null === $confidence ? null : number_format(max(0.0, min(1.0, $confidence)), 4, '.', '');
        $pages = array_map('intval', $pageReferences);
        $pages = array_values(array_unique(array_filter($pages, static fn (int $page): bool => $page > 0)));
        sort($pages);
        $this->pageReferences = $pages;
        $extraction->addSuggestion($this);
    }

    public function id(): string
    {
        return (string) $this->id;
    }

    public function extraction(): DocumentExtraction
    {
        return $this->extraction;
    }

    public function fieldPath(): string
    {
        return $this->fieldPath;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function valueType(): string
    {
        return $this->valueType;
    }

    public function rawValue(): mixed
    {
        return $this->rawValue;
    }

    public function correctedValue(): mixed
    {
        return $this->correctedValue;
    }

    public function observedValue(): mixed
    {
        return $this->observedValue;
    }

    public function confidence(): ?float
    {
        return null === $this->confidence ? null : (float) $this->confidence;
    }

    /** @return list<int> */
    public function pageReferences(): array
    {
        return $this->pageReferences;
    }

    public function status(): DecisionStatus
    {
        return $this->status;
    }

    public function isPending(): bool
    {
        return DecisionStatus::Pending === $this->status;
    }

    public function decidedBy(): ?string
    {
        return $this->decidedBy;
    }

    public function decidedAt(): ?\DateTimeImmutable
    {
        return $this->decidedAt;
    }

    public function correct(mixed $value): void
    {
        if (!$this->isPending()) {
            throw new \DomainException('Une décision OCR sauvegardée est immuable.');
        }
        $this->correctedValue = $value;
    }

    public function decide(DecisionStatus $status, string $actor): void
    {
        if (!$this->isPending()) {
            throw new \DomainException('Une décision OCR sauvegardée est immuable.');
        }
        if (DecisionStatus::Pending === $status) {
            return;
        }
        $this->status = $status;
        $this->decidedBy = $actor;
        $this->decidedAt = new \DateTimeImmutable();
    }
}
