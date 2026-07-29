<?php

declare(strict_types=1);

namespace App\Pim\Entity;

use App\Pim\Enum\StatutFiche;
use App\Pim\Enum\TypeFiche;
use App\Pim\Repository\FicheRepository;
use App\Shared\Entity\TimestampableTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: FicheRepository::class)]
#[ORM\Table(name: 'pim_fiche')]
#[ORM\UniqueConstraint(name: 'UNIQ_PIM_FICHE_TYPE_CODE', columns: ['type', 'code'])]
#[ORM\Index(name: 'IDX_PIM_FICHE_TYPE_UPDATED', columns: ['type', 'updated_at', 'id'])]
#[ORM\Index(name: 'IDX_PIM_FICHE_TYPE_STATUS_UPDATED', columns: ['type', 'status', 'updated_at', 'id'])]
#[ORM\Index(name: 'IDX_PIM_FICHE_STATUS_UPDATED', columns: ['status', 'updated_at', 'id'])]
#[ORM\HasLifecycleCallbacks]
class Fiche
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    private Ulid $id;

    #[ORM\Column(length: 32, enumType: TypeFiche::class)]
    private TypeFiche $type;

    #[ORM\Column(options: ['unsigned' => true], nullable: true)]
    private ?int $code = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $label = null;

    #[ORM\Column(length: 16, enumType: StatutFiche::class)]
    private StatutFiche $status = StatutFiche::EnCours;

    #[ORM\Column(type: Types::SMALLINT, options: ['unsigned' => true, 'default' => 0])]
    private int $completeness = 0;

    #[ORM\Version]
    #[ORM\Column(type: Types::INTEGER, options: ['unsigned' => true, 'default' => 1])]
    private int $version = 1;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $archivedAt = null;

    #[ORM\OneToOne(cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\JoinColumn(name: 'localisation_id', referencedColumnName: 'id', nullable: true, unique: true, onDelete: 'SET NULL')]
    private ?Localisation $localisation = null;

    /** @var Collection<int, FicheAttributValeur> */
    #[ORM\OneToMany(mappedBy: 'fiche', targetEntity: FicheAttributValeur::class, cascade: ['persist', 'remove'], orphanRemoval: true, fetch: 'EXTRA_LAZY')]
    private Collection $attributValues;

    public function __construct(TypeFiche $type, ?Ulid $id = null)
    {
        $this->id = $id ?? new Ulid();
        $this->type = $type;
        $this->attributValues = new ArrayCollection();
        $this->initializeTimestamps();
    }

    public function id(): Ulid { return $this->id; }
    public function idString(): string { return (string) $this->id; }
    public function type(): TypeFiche { return $this->type; }
    public function code(): ?int { return $this->code; }
    public function label(): ?string { return $this->label; }
    public function status(): StatutFiche { return $this->status; }
    public function completeness(): int { return $this->completeness; }
    public function version(): int { return $this->version; }
    public function publishedAt(): ?\DateTimeImmutable { return $this->publishedAt; }
    public function archivedAt(): ?\DateTimeImmutable { return $this->archivedAt; }
    public function localisation(): ?Localisation { return $this->localisation; }

    public function changeCode(?int $code): void { $this->code = $code; $this->markChanged(); }
    public function changeLabel(?string $label): void { $this->label = self::normalize($label); $this->markChanged(); }
    public function changeCompleteness(int $value): void
    {
        if ($value < 0 || $value > 100) {
            throw new \InvalidArgumentException('La complétude doit être comprise entre 0 et 100.');
        }
        $this->completeness = $value;
        $this->markChanged();
    }
    public function changeLocalisation(?Localisation $localisation): void { $this->localisation = $localisation; $this->markChanged(); }
    public function changeStatus(StatutFiche $status): void
    {
        $this->status = $status;
        $this->publishedAt = StatutFiche::Publiee === $status ? ($this->publishedAt ?? new \DateTimeImmutable()) : $this->publishedAt;
        $this->archivedAt = StatutFiche::Archivee === $status ? new \DateTimeImmutable() : null;
        $this->markChanged();
    }

    /** @return list<int> */
    public function valueIdsFor(string $attributeCode): array
    {
        return array_values(array_map(
            static fn (FicheAttributValeur $link): int => $link->valueId(),
            $this->attributValues->filter(static fn (FicheAttributValeur $link): bool => $link->attributeCode() === $attributeCode)->toArray(),
        ));
    }

    /** @param list<int> $valueIds */
    public function replaceAttributeValues(string $attributeCode, array $valueIds): void
    {
        $valueIds = array_values(array_unique($valueIds));
        $requested = array_fill_keys($valueIds, true);
        $current = [];

        // Iterating initializes the persistent collection once; subsequent replacements
        // in the same request reuse that in-memory collection.
        foreach ($this->attributValues as $link) {
            if ($link->attributeCode() !== $attributeCode) {
                continue;
            }

            $current[$link->valueId()] = true;
            if (!isset($requested[$link->valueId()])) {
                $this->attributValues->removeElement($link);
            }
        }

        foreach ($valueIds as $valueId) {
            if (!isset($current[$valueId])) {
                $this->attributValues->add(new FicheAttributValeur($this, $attributeCode, $valueId));
            }
        }
        $this->markChanged();
    }

    public function markChanged(): void { $this->touch(); }

    private static function normalize(?string $value): ?string
    {
        if (null === $value) { return null; }
        $value = trim($value);
        return '' === $value ? null : $value;
    }
}
