<?php

declare(strict_types=1);

namespace App\Audit\Entity;

use App\Audit\Repository\AuditRevisionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: AuditRevisionRepository::class)]
#[ORM\Table(name: 'audit_revision')]
#[
    ORM\Index(
        name: 'IDX_AUDIT_REVISION_FICHE_DATE',
        columns: ['fiche_id', 'created_at', 'id'],
    ),
]
final class AuditRevision
{
    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    private Ulid $id;
    #[ORM\Column(type: 'ulid')]
    private Ulid $ficheId;
    #[ORM\Column(length: 64)]
    private string $action;
    #[ORM\Column(length: 32)]
    private string $source;
    #[ORM\Column(length: 255)]
    private string $actor;
    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $rolesScopes;
    #[ORM\Column(length: 128)]
    private string $correlationId;
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;
    /** @var Collection<int, AuditChange> */
    #[
        ORM\OneToMany(
            mappedBy: 'revision',
            targetEntity: AuditChange::class,
            cascade: ['persist'],
            orphanRemoval: true,
        ),
    ]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $changes;

    /** @param list<string> $rolesScopes */
    public function __construct(
        string $ficheId,
        string $action,
        string $source,
        string $actor,
        array $rolesScopes,
        string $correlationId,
    ) {
        $this->id = new Ulid();
        $this->ficheId = Ulid::fromString($ficheId);
        $this->action = $action;
        $this->source = $source;
        $this->actor = mb_substr($actor, 0, 255);
        $this->rolesScopes = array_values(array_unique($rolesScopes));
        $this->correlationId = mb_substr($correlationId, 0, 128);
        $this->createdAt = new \DateTimeImmutable();
        $this->changes = new ArrayCollection();
    }

    public function id(): string
    {
        return (string) $this->id;
    }

    public function ficheId(): string
    {
        return (string) $this->ficheId;
    }

    public function action(): string
    {
        return $this->action;
    }

    public function source(): string
    {
        return $this->source;
    }

    public function actor(): string
    {
        return $this->actor;
    }

    /** @return list<string> */
    public function rolesScopes(): array
    {
        return $this->rolesScopes;
    }

    public function correlationId(): string
    {
        return $this->correlationId;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, AuditChange> */
    public function changes(): Collection
    {
        return $this->changes;
    }

    public function changeAction(string $action): void
    {
        $this->action = $action;
    }

    public function changeSource(string $source): void
    {
        $this->source = $source;
    }

    public function addChange(AuditChange $change): void
    {
        if (!$this->changes->contains($change)) {
            $this->changes->add($change);
        }
    }
}
