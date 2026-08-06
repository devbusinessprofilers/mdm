<?php

declare(strict_types=1);

namespace App\Etl\Entity;

use App\Etl\Repository\LegacyFicheMappingRepository;
use App\Shared\Entity\TimestampableTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

/**
 * Correspondance entre l'Id syspad du legacy et la fiche PIM créée par
 * l'import production. Le code fiche étant attribué par trigger SQL, cette
 * table est le seul pivot fiable pour l'idempotence des ré-imports, la
 * reprise des photos et la future reprise des traductions (bp-dump).
 */
#[ORM\Entity(repositoryClass: LegacyFicheMappingRepository::class)]
#[ORM\Table(name: 'etl_legacy_fiche')]
#[ORM\UniqueConstraint(name: 'UNIQ_ETL_LEGACY_FICHE_FICHE', columns: ['fiche_id'])]
#[ORM\HasLifecycleCallbacks]
class LegacyFicheMapping
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\Column(options: ['unsigned' => true])]
    private int $syspadId;

    #[ORM\Column(type: 'ulid')]
    private Ulid $ficheId;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $label;

    #[ORM\Column(length: 32)]
    private string $gamme;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $photosJson;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $photosSeededAt = null;

    public function __construct(int $syspadId, Ulid $ficheId, ?string $label, string $gamme, ?string $photosJson)
    {
        $this->syspadId = $syspadId;
        $this->ficheId = $ficheId;
        $this->label = null === $label ? null : mb_substr($label, 0, 255);
        $this->gamme = mb_substr($gamme, 0, 32);
        $this->photosJson = '' === $photosJson ? null : $photosJson;
        $this->initializeTimestamps();
    }

    public function syspadId(): int { return $this->syspadId; }
    public function ficheId(): Ulid { return $this->ficheId; }
    public function label(): ?string { return $this->label; }
    public function gamme(): string { return $this->gamme; }
    public function photosJson(): ?string { return $this->photosJson; }
    public function photosSeededAt(): ?\DateTimeImmutable { return $this->photosSeededAt; }

    public function refresh(?string $label, string $gamme, ?string $photosJson): void
    {
        $this->label = null === $label ? null : mb_substr($label, 0, 255);
        $this->gamme = mb_substr($gamme, 0, 32);
        $this->photosJson = '' === $photosJson ? null : $photosJson;
        $this->touch();
    }

    public function markPhotosSeeded(): void
    {
        $this->photosSeededAt = new \DateTimeImmutable();
        $this->touch();
    }
}
