<?php

declare(strict_types=1);

namespace App\Pim\Entity;

use App\Pim\Repository\TextFingerprintRepository;
use App\Shared\Entity\TimestampableTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

/**
 * Empreinte d'un champ de texte libre d'une fiche : hash exact du texte
 * normalisé + SimHash 64 bits pour le quasi-doublon. Équivalent texte du
 * couple checksum / pHash porté par MediaAsset côté DAM.
 */
#[ORM\Entity(repositoryClass: TextFingerprintRepository::class)]
#[ORM\Table(name: 'pim_text_fingerprint')]
#[ORM\UniqueConstraint(name: 'UNIQ_PIM_TEXT_FP_FIELD', columns: ['fiche_id', 'field_path'])]
#[ORM\Index(name: 'IDX_PIM_TEXT_FP_EXACT', columns: ['exact_hash'])]
#[ORM\Index(name: 'IDX_PIM_TEXT_FP_TYPE', columns: ['fiche_type'])]
#[ORM\HasLifecycleCallbacks]
class TextFingerprint
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    private Ulid $id;

    #[ORM\Column(length: 26)]
    private string $ficheId;

    #[ORM\Column(length: 32)]
    private string $ficheType;

    #[ORM\Column(length: 191)]
    private string $fieldPath;

    #[ORM\Column(length: 255)]
    private string $fieldLabel;

    /** SHA-256 hexadécimal du texte normalisé. */
    #[ORM\Column(length: 64)]
    private string $exactHash;

    /** SimHash 64 bits, en hexadécimal 16 caractères (même format que le pHash). */
    #[ORM\Column(length: 16)]
    private string $simhash;

    #[ORM\Column]
    private int $length;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $snippet;

    public function __construct(
        string $ficheId,
        string $ficheType,
        string $fieldPath,
        string $fieldLabel,
        string $exactHash,
        string $simhash,
        int $length,
        ?string $snippet,
    ) {
        $this->id = new Ulid();
        $this->ficheId = $ficheId;
        $this->ficheType = $ficheType;
        $this->fieldPath = $fieldPath;
        $this->fieldLabel = $fieldLabel;
        $this->exactHash = $exactHash;
        $this->simhash = $simhash;
        $this->length = $length;
        $this->snippet = $snippet;
        $this->initializeTimestamps();
    }

    public function id(): string { return (string) $this->id; }
    public function ficheId(): string { return $this->ficheId; }
    public function ficheType(): string { return $this->ficheType; }
    public function fieldPath(): string { return $this->fieldPath; }
    public function fieldLabel(): string { return $this->fieldLabel; }
    public function exactHash(): string { return $this->exactHash; }
    public function simhash(): string { return $this->simhash; }
    public function length(): int { return $this->length; }
    public function snippet(): ?string { return $this->snippet; }

    /** Réaligne l'empreinte sur le texte courant. Renvoie true si le contenu a changé. */
    public function refresh(string $fieldLabel, string $exactHash, string $simhash, int $length, ?string $snippet): bool
    {
        $changed = $this->exactHash !== $exactHash || $this->simhash !== $simhash;
        $this->fieldLabel = $fieldLabel;
        $this->exactHash = $exactHash;
        $this->simhash = $simhash;
        $this->length = $length;
        $this->snippet = $snippet;
        $this->touch();

        return $changed;
    }
}
