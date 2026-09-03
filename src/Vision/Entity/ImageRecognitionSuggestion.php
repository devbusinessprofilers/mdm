<?php

declare(strict_types=1);

namespace App\Vision\Entity;

use App\Vision\Enum\SuggestionStatus;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

/**
 * Un champ déduit de l'image par la reconnaissance : légende, mots-clés ou
 * information de contexte (type de vue, intérieur/extérieur). Comme pour
 * l'OCR, une décision sauvegardée est immuable.
 */
#[ORM\Entity]
#[ORM\Table(name: 'vision_image_recognition_suggestion')]
#[ORM\UniqueConstraint(name: 'UNIQ_VISION_RECO_SUGGESTION_PATH', columns: ['recognition_id', 'field_path'])]
final class ImageRecognitionSuggestion
{
    public const PATH_LEGENDE = 'legende';
    public const PATH_KEYWORDS = 'keywords';
    public const PATH_VUE_TYPE = 'extras.vue_type';
    public const PATH_INTERIEUR_EXTERIEUR = 'extras.interieur_exterieur';

    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    private Ulid $id;

    #[ORM\ManyToOne(inversedBy: 'suggestions', fetch: 'EAGER')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ImageRecognition $recognition;

    #[ORM\Column(name: 'field_path', length: 255)]
    private string $fieldPath;

    #[ORM\Column(length: 255)]
    private string $label;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private mixed $rawValue;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private mixed $correctedValue;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private mixed $observedValue;

    #[ORM\Column(length: 16, enumType: SuggestionStatus::class)]
    private SuggestionStatus $status = SuggestionStatus::Pending;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $decidedBy = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $decidedAt = null;

    public function __construct(ImageRecognition $recognition, string $path, string $label, mixed $rawValue, mixed $observedValue)
    {
        $this->id = new Ulid();
        $this->recognition = $recognition;
        $this->fieldPath = $path;
        $this->label = mb_substr($label, 0, 255);
        $this->rawValue = $rawValue;
        $this->correctedValue = $rawValue;
        $this->observedValue = $observedValue;
        $recognition->addSuggestion($this);
    }

    public function id(): string
    {
        return (string) $this->id;
    }

    public function recognition(): ImageRecognition
    {
        return $this->recognition;
    }

    public function fieldPath(): string
    {
        return $this->fieldPath;
    }

    public function label(): string
    {
        return $this->label;
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

    public function status(): SuggestionStatus
    {
        return $this->status;
    }

    public function isPending(): bool
    {
        return SuggestionStatus::Pending === $this->status;
    }

    public function isAccepted(): bool
    {
        return SuggestionStatus::Accepted === $this->status;
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
            throw new \DomainException('Une décision de reconnaissance sauvegardée est immuable.');
        }
        $this->correctedValue = $value;
    }

    public function decide(SuggestionStatus $status, string $actor): void
    {
        if (!$this->isPending()) {
            throw new \DomainException('Une décision de reconnaissance sauvegardée est immuable.');
        }
        if (SuggestionStatus::Pending === $status) {
            return;
        }
        $this->status = $status;
        $this->decidedBy = $actor;
        $this->decidedAt = new \DateTimeImmutable();
    }
}
