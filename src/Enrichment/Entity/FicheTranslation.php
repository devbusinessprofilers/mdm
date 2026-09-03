<?php

declare(strict_types=1);

namespace App\Enrichment\Entity;

use App\Enrichment\Enum\SupportedLocale;
use App\Enrichment\Enum\TranslationOrigin;
use App\Enrichment\Enum\TranslationStatus;
use App\Enrichment\Repository\FicheTranslationRepository;
use App\Pim\Entity\Fiche;
use App\Shared\Entity\TimestampableTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: FicheTranslationRepository::class)]
#[ORM\Table(name: 'enrichment_fiche_translation')]
#[ORM\UniqueConstraint(name: 'UNIQ_ENRICHMENT_FICHE_FIELD_LOCALE', columns: ['fiche_id', 'field_path', 'locale'])]
#[ORM\Index(name: 'IDX_ENRICHMENT_FICHE_TRANSLATION_STATUS', columns: ['status', 'updated_at'])]
// Tri « tous statuts » du journal /outils (556k lignes : sans index, filesort).
#[ORM\Index(name: 'IDX_ENRICHMENT_FICHE_TRANSLATION_UPDATED', columns: ['updated_at'])]
#[ORM\HasLifecycleCallbacks]
class FicheTranslation
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    private Ulid $id;
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Fiche $fiche;
    #[ORM\Column(length: 191)]
    private string $fieldPath;
    #[ORM\Column(length: 255)]
    private string $fieldLabel;
    #[ORM\Column(length: 5, enumType: SupportedLocale::class)]
    private SupportedLocale $locale;
    #[ORM\Column(type: Types::TEXT)]
    private string $sourceText;
    #[ORM\Column(length: 64)]
    private string $sourceHash;
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $translatedText = null;
    #[ORM\Column(length: 64, nullable: true)]
    private ?string $translatedSourceHash = null;
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $suggestedText = null;
    #[ORM\Column(length: 16, enumType: TranslationOrigin::class, nullable: true)]
    private ?TranslationOrigin $origin = null;
    #[ORM\Column(length: 16, enumType: TranslationStatus::class)]
    private TranslationStatus $status = TranslationStatus::Pending;
    #[ORM\Column(length: 26, nullable: true)]
    private ?string $requestToken = null;
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $lastError = null;
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $manuallyUpdatedBy = null;

    public function __construct(Fiche $fiche, string $path, string $label, SupportedLocale $locale, string $source)
    {
        $this->id = new Ulid();
        $this->fiche = $fiche;
        $this->fieldPath = $path;
        $this->fieldLabel = $label;
        $this->locale = $locale;
        $this->sourceText = $source;
        $this->sourceHash = self::hash($source);
        $this->initializeTimestamps();
    }

    public function id(): string
    {
        return (string) $this->id;
    }

    public function fiche(): Fiche
    {
        return $this->fiche;
    }

    public function fieldPath(): string
    {
        return $this->fieldPath;
    }

    public function fieldLabel(): string
    {
        return $this->fieldLabel;
    }

    public function locale(): SupportedLocale
    {
        return $this->locale;
    }

    public function sourceText(): string
    {
        return $this->sourceText;
    }

    public function translatedText(): ?string
    {
        return $this->translatedText;
    }

    public function suggestedText(): ?string
    {
        return $this->suggestedText;
    }

    public function origin(): ?TranslationOrigin
    {
        return $this->origin;
    }

    public function status(): TranslationStatus
    {
        return $this->status;
    }

    public function requestToken(): ?string
    {
        return $this->requestToken;
    }

    public function lastError(): ?string
    {
        return $this->lastError;
    }

    public function schedule(string $label, string $source, string $token): bool
    {
        $newHash = self::hash($source);
        $changed = $newHash !== $this->sourceHash;
        $this->fieldLabel = $label;
        $this->sourceText = $source;
        $this->sourceHash = $newHash;
        if (!$changed && TranslationStatus::Available === $this->status) {
            return false;
        }
        if (TranslationOrigin::Manual === $this->origin && null !== $this->translatedText) {
            $this->status = TranslationStatus::Obsolete;
        } else {
            $this->status = TranslationStatus::Pending;
        }
        $this->requestToken = $token;
        $this->lastError = null;
        $this->touch();

        return true;
    }

    public function applyGoogle(string $value, string $token): void
    {
        if ($token !== $this->requestToken) {
            return;
        }
        if (TranslationOrigin::Manual === $this->origin && null !== $this->translatedText) {
            $this->suggestedText = trim($value);
            $this->status = TranslationStatus::Obsolete;
        } else {
            $this->translatedText = trim($value);
            $this->translatedSourceHash = $this->sourceHash;
            $this->origin = TranslationOrigin::Google;
            $this->suggestedText = null;
            $this->status = TranslationStatus::Available;
        }
        $this->requestToken = null;
        $this->lastError = null;
        $this->touch();
    }

    public function applyManual(string $value, string $actor): void
    {
        $this->translatedText = trim($value);
        $this->translatedSourceHash = $this->sourceHash;
        $this->origin = TranslationOrigin::Manual;
        $this->status = TranslationStatus::Available;
        $this->suggestedText = null;
        $this->requestToken = null;
        $this->lastError = null;
        $this->manuallyUpdatedBy = mb_substr($actor, 0, 255);
        $this->touch();
    }

    public function fail(string $token, string $error): void
    {
        if ($token !== $this->requestToken) {
            return;
        }
        $this->status = TranslationStatus::Failed;
        $this->lastError = mb_substr($error, 0, 2000);
        $this->requestToken = null;
        $this->touch();
    }

    public function markObsolete(): void
    {
        $this->status = TranslationStatus::Obsolete;
        $this->requestToken = null;
        $this->touch();
    }

    private static function hash(string $value): string
    {
        return hash('sha256', $value);
    }
}
