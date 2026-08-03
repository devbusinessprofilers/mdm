<?php

declare(strict_types=1);

namespace App\Enrichment\Entity;

use App\Enrichment\Enum\SupportedLocale;
use App\Enrichment\Enum\TranslationOrigin;
use App\Enrichment\Enum\TranslationStatus;
use App\Shared\Entity\TimestampableTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\MappedSuperclass]
#[ORM\HasLifecycleCallbacks]
abstract class AbstractLovTranslation
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    protected Ulid $id;
    #[ORM\Column(length: 5, enumType: SupportedLocale::class)]
    protected SupportedLocale $locale;
    #[ORM\Column(length: 255)]
    protected string $sourceLabel;
    #[ORM\Column(length: 64)]
    protected string $sourceHash;
    #[ORM\Column(length: 255, nullable: true)]
    protected ?string $translatedLabel = null;
    #[ORM\Column(length: 64, nullable: true)]
    protected ?string $translatedSourceHash = null;
    #[ORM\Column(length: 255, nullable: true)]
    protected ?string $suggestedLabel = null;
    #[ORM\Column(length: 16, enumType: TranslationOrigin::class, nullable: true)]
    protected ?TranslationOrigin $origin = null;
    #[ORM\Column(length: 16, enumType: TranslationStatus::class)]
    protected TranslationStatus $status = TranslationStatus::Pending;
    #[ORM\Column(length: 26, nullable: true)]
    protected ?string $requestToken = null;
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    protected ?string $lastError = null;
    #[ORM\Column(length: 255, nullable: true)]
    protected ?string $manuallyUpdatedBy = null;

    protected function initializeTranslation(SupportedLocale $locale, string $source): void
    {
        $this->id = new Ulid();
        $this->locale = $locale;
        $this->sourceLabel = $source;
        $this->sourceHash = hash('sha256', $source);
        $this->initializeTimestamps();
    }

    public function id(): string { return (string) $this->id; }
    public function locale(): SupportedLocale { return $this->locale; }
    public function sourceLabel(): string { return $this->sourceLabel; }
    public function translatedLabel(): ?string { return $this->translatedLabel; }
    public function suggestedLabel(): ?string { return $this->suggestedLabel; }
    public function origin(): ?TranslationOrigin { return $this->origin; }
    public function status(): TranslationStatus { return $this->status; }
    public function requestToken(): ?string { return $this->requestToken; }
    public function lastError(): ?string { return $this->lastError; }

    public function schedule(string $source, string $token): bool
    {
        $hash = hash('sha256', $source);
        $changed = $hash !== $this->sourceHash;
        $this->sourceLabel = $source;
        $this->sourceHash = $hash;
        if (!$changed && TranslationStatus::Available === $this->status) { return false; }
        $this->status = TranslationOrigin::Manual === $this->origin && null !== $this->translatedLabel
            ? TranslationStatus::Obsolete
            : TranslationStatus::Pending;
        $this->requestToken = $token;
        $this->lastError = null;
        $this->touch();

        return true;
    }

    public function applyGoogle(string $value, string $token): void
    {
        if ($token !== $this->requestToken) { return; }
        if (TranslationOrigin::Manual === $this->origin && null !== $this->translatedLabel) {
            $this->suggestedLabel = trim($value);
            $this->status = TranslationStatus::Obsolete;
        } else {
            $this->translatedLabel = trim($value);
            $this->translatedSourceHash = $this->sourceHash;
            $this->origin = TranslationOrigin::Google;
            $this->suggestedLabel = null;
            $this->status = TranslationStatus::Available;
        }
        $this->requestToken = null;
        $this->lastError = null;
        $this->touch();
    }

    public function applyManual(string $value, string $actor): void
    {
        $this->translatedLabel = trim($value);
        $this->translatedSourceHash = $this->sourceHash;
        $this->origin = TranslationOrigin::Manual;
        $this->status = TranslationStatus::Available;
        $this->suggestedLabel = null;
        $this->requestToken = null;
        $this->lastError = null;
        $this->manuallyUpdatedBy = mb_substr($actor, 0, 255);
        $this->touch();
    }

    public function fail(string $token, string $error): void
    {
        if ($token !== $this->requestToken) { return; }
        $this->status = TranslationStatus::Failed;
        $this->lastError = mb_substr($error, 0, 2000);
        $this->requestToken = null;
        $this->touch();
    }
}
