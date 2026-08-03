<?php

declare(strict_types=1);

namespace App\Pim\Entity;

use App\Pim\Entity\Lieu\RessourceLieu;
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
#[
    ORM\UniqueConstraint(
        name: 'UNIQ_PIM_FICHE_CODE',
        columns: ['code'],
    ),
]
#[
    ORM\Index(
        name: 'IDX_PIM_FICHE_TYPE_UPDATED',
        columns: ['type', 'updated_at', 'id'],
    ),
]
#[ORM\Index(name: 'IDX_PIM_FICHE_TYPE_ID', columns: ['type', 'id'])]
#[
    ORM\Index(
        name: 'IDX_PIM_FICHE_TYPE_STATUS_UPDATED',
        columns: ['type', 'status', 'updated_at', 'id'],
    ),
]
#[
    ORM\Index(
        name: 'IDX_PIM_FICHE_STATUS_UPDATED',
        columns: ['status', 'updated_at', 'id'],
    ),
]
#[ORM\HasLifecycleCallbacks]
class Fiche
{
    use TimestampableTrait;
    /** Transient guard used by technical/API updates that must preserve workflow state. */
    private int $workflowTransitionSuppressionDepth = 0;
    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    private Ulid $id;
    #[ORM\Column(length: 32, enumType: TypeFiche::class)]
    private TypeFiche $type;
    #[
        ORM\Column(
            options: ['unsigned' => true],
            insertable: false,
            updatable: false,
            generated: 'INSERT',
        ),
    ]
    private ?int $code = null;
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $label = null;
    #[ORM\Column(length: 32, enumType: StatutFiche::class)]
    private StatutFiche $status = StatutFiche::EnCours;
    #[ORM\Version]
    #[
        ORM\Column(
            type: Types::INTEGER,
            options: ['unsigned' => true, 'default' => 1],
        ),
    ]
    private int $version = 1;
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $archivedAt = null;
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $validationRequestedAt = null;
    #[ORM\Column(length: 26, nullable: true)]
    private ?string $validationRequestedBy = null;
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $validationReviewedAt = null;
    #[ORM\Column(length: 26, nullable: true)]
    private ?string $validationReviewedBy = null;
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $validationFeedback = null;
    #[ORM\OneToOne(cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[
        ORM\JoinColumn(
            name: 'localisation_id',
            referencedColumnName: 'id',
            nullable: true,
            unique: true,
            onDelete: 'SET NULL',
        ),
    ]
    private ?Localisation $localisation = null;
    /** @var Collection<int, FicheAttributValeur> */
    #[
        ORM\OneToMany(
            mappedBy: 'fiche',
            targetEntity: FicheAttributValeur::class,
            cascade: ['persist', 'remove'],
            orphanRemoval: true,
            fetch: 'EXTRA_LAZY',
        ),
    ]
    private Collection $attributValues;
    /** @var Collection<int, RessourceLieu> */
    #[
        ORM\OneToMany(
            mappedBy: 'fiche',
            targetEntity: RessourceLieu::class,
            cascade: ['persist', 'remove'],
            orphanRemoval: true,
        ),
    ]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $resources;

    public function __construct(TypeFiche $type, ?Ulid $id = null)
    {
        $this->id = $id ?? new Ulid();
        $this->type = $type;
        $this->attributValues = new ArrayCollection();
        $this->resources = new ArrayCollection();
        $this->initializeTimestamps();
    }

    public function id(): Ulid
    {
        return $this->id;
    }

    public function idString(): string
    {
        return (string) $this->id;
    }

    public function type(): TypeFiche
    {
        return $this->type;
    }

    public function code(): int
    {
        if (null === $this->code) {
            throw new \LogicException(
                'Le code de la fiche sera attribué lors de son enregistrement.',
            );
        }

        return $this->code;
    }

    public function label(): ?string
    {
        return $this->label;
    }

    public function status(): StatutFiche
    {
        return $this->status;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function publishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function archivedAt(): ?\DateTimeImmutable
    {
        return $this->archivedAt;
    }

    public function validationRequestedAt(): ?\DateTimeImmutable
    {
        return $this->validationRequestedAt;
    }

    public function validationRequestedBy(): ?string
    {
        return $this->validationRequestedBy;
    }

    public function validationReviewedAt(): ?\DateTimeImmutable
    {
        return $this->validationReviewedAt;
    }

    public function validationReviewedBy(): ?string
    {
        return $this->validationReviewedBy;
    }

    public function validationFeedback(): ?string
    {
        return $this->validationFeedback;
    }

    public function localisation(): ?Localisation
    {
        return $this->localisation;
    }

    /** @return Collection<int, RessourceLieu> */
    public function resources(): Collection
    {
        return $this->resources;
    }

    public function addResource(RessourceLieu $resource): void
    {
        if (!$this->resources->contains($resource)) {
            $this->resources->add($resource);
            $resource->attachToFiche($this);
            $this->markChanged();
        }
    }

    public function removeResource(RessourceLieu $resource): void
    {
        if ($this->resources->removeElement($resource)) {
            $this->markChanged();
        }
    }

    public function changeLabel(?string $label): void
    {
        $this->label = self::normalize($label);
        $this->markChanged();
    }

    public function changeLocalisation(?Localisation $localisation): void
    {
        $this->localisation = $localisation;
        $this->markChanged();
    }

    public function submitForValidation(string $actorId): void
    {
        if (StatutFiche::EnCours !== $this->status) {
            throw new \DomainException('Seule une fiche en cours peut être soumise à validation.');
        }
        $this->status = StatutFiche::EnAttenteValidation;
        $this->validationRequestedAt = new \DateTimeImmutable();
        $this->validationRequestedBy = $actorId;
        $this->validationReviewedAt = null;
        $this->validationReviewedBy = null;
        $this->validationFeedback = null;
        $this->touch();
    }

    public function validateAndPublish(string $actorId): void
    {
        if (StatutFiche::EnAttenteValidation !== $this->status) {
            throw new \DomainException('Seule une fiche en attente peut être validée.');
        }
        $this->status = StatutFiche::Publiee;
        $this->publishedAt = new \DateTimeImmutable();
        $this->validationReviewedAt = new \DateTimeImmutable();
        $this->validationReviewedBy = $actorId;
        $this->validationFeedback = null;
        $this->touch();
    }

    public function rejectValidation(string $actorId, string $reason): void
    {
        $reason = trim($reason);
        if (StatutFiche::EnAttenteValidation !== $this->status) {
            throw new \DomainException('Seule une fiche en attente peut être refusée.');
        }
        if ('' === $reason) {
            throw new \DomainException('Le motif du refus est obligatoire.');
        }
        $this->status = StatutFiche::EnCours;
        $this->validationReviewedAt = new \DateTimeImmutable();
        $this->validationReviewedBy = $actorId;
        $this->validationFeedback = $reason;
        $this->touch();
    }

    public function archive(string $actorId): void
    {
        if (StatutFiche::Publiee !== $this->status) {
            throw new \DomainException('Seule une fiche publiée peut être archivée.');
        }
        $this->status = StatutFiche::Archivee;
        $this->archivedAt = new \DateTimeImmutable();
        $this->validationReviewedAt = new \DateTimeImmutable();
        $this->validationReviewedBy = $actorId;
        $this->touch();
    }

    /** Publication technique réservée aux imports et aux jeux de données internes. */
    public function publishForImport(): void
    {
        $this->status = StatutFiche::Publiee;
        $this->publishedAt = new \DateTimeImmutable();
        $this->archivedAt = null;
        $this->validationFeedback = null;
        $this->validationReviewedAt = null;
        $this->validationReviewedBy = null;
        $this->touch();
    }

    /** @return list<int> */
    public function valueIdsFor(string $attributeCode): array
    {
        return array_values(
            array_map(
                static fn (FicheAttributValeur $link): int => $link->valueId(),
                $this->attributValues
                    ->filter(
                        static fn (
                            FicheAttributValeur $link,
                        ): bool => $link->attributeCode() === $attributeCode,
                    )
                    ->toArray(),
            ),
        );
    }

    /** @param list<int> $valueIds */
    public function replaceAttributeValues(
        string $attributeCode,
        array $valueIds,
    ): void {
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
                $this->attributValues->add(
                    new FicheAttributValeur($this, $attributeCode, $valueId),
                );
            }
        }
        $this->markChanged();
    }

    public function markChanged(): void
    {
        if ($this->workflowTransitionSuppressionDepth > 0) {
            $this->touch();

            return;
        }
        if (StatutFiche::EnCours !== $this->status) {
            $this->status = StatutFiche::EnCours;
            $this->archivedAt = null;
            $this->validationRequestedAt = null;
            $this->validationRequestedBy = null;
            $this->validationReviewedAt = null;
            $this->validationReviewedBy = null;
        }
        $this->touch();
    }

    /** Mise à jour technique sans modifier le cycle de validation. */
    public function markSystemChanged(): void
    {
        $this->touch();
    }

    /**
     * Exécute une modification en conservant le statut et toutes les métadonnées
     * du workflow. La version et la date de modification restent actualisées.
     *
     * @template T
     *
     * @param callable(): T $mutation
     *
     * @return T
     */
    public function preserveWorkflowDuring(callable $mutation): mixed
    {
        $workflow = [
            $this->status,
            $this->publishedAt,
            $this->archivedAt,
            $this->validationRequestedAt,
            $this->validationRequestedBy,
            $this->validationReviewedAt,
            $this->validationReviewedBy,
            $this->validationFeedback,
        ];
        ++$this->workflowTransitionSuppressionDepth;
        try {
            return $mutation();
        } finally {
            --$this->workflowTransitionSuppressionDepth;
            [
                $this->status,
                $this->publishedAt,
                $this->archivedAt,
                $this->validationRequestedAt,
                $this->validationRequestedBy,
                $this->validationReviewedAt,
                $this->validationReviewedBy,
                $this->validationFeedback,
            ] = $workflow;
        }
    }

    private static function normalize(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }
        $value = trim($value);

        return '' === $value ? null : $value;
    }
}
