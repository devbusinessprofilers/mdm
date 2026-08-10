<?php

declare(strict_types=1);

namespace App\Pim\Entity;

use App\Pim\Repository\SavedViewRepository;
use App\Shared\Entity\TimestampableTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Vue enregistrée de la liste du référentiel : un nom posé sur un jeu de
 * filtres. Personnelle par défaut, partagée à toute l'équipe sur demande ;
 * seul son auteur la modifie ou la supprime.
 */
#[ORM\Entity(repositoryClass: SavedViewRepository::class)]
#[ORM\Table(name: 'pim_saved_view')]
#[ORM\Index(name: 'IDX_PIM_SAVED_VIEW_OWNER', columns: ['owner_id', 'shared'])]
#[ORM\HasLifecycleCallbacks]
class SavedView
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    private string $name;

    #[ORM\Column(name: 'owner_id', length: 26)]
    private string $ownerId;

    #[ORM\Column(name: 'owner_label', length: 180)]
    private string $ownerLabel;

    #[ORM\Column(options: ['default' => false])]
    private bool $shared = false;

    /** @var array<string, mixed> Paramètres de filtre tels que soumis par le formulaire. */
    #[ORM\Column(type: Types::JSON)]
    private array $filters = [];

    /** @param array<string, mixed> $filters */
    public function __construct(string $name, string $ownerId, string $ownerLabel, array $filters, bool $shared = false)
    {
        $this->name = trim($name);
        $this->ownerId = $ownerId;
        $this->ownerLabel = $ownerLabel;
        $this->filters = $filters;
        $this->shared = $shared;
        $this->initializeTimestamps();
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function ownerId(): string
    {
        return $this->ownerId;
    }

    public function ownerLabel(): string
    {
        return $this->ownerLabel;
    }

    public function shared(): bool
    {
        return $this->shared;
    }

    /** @return array<string, mixed> */
    public function filters(): array
    {
        return $this->filters;
    }

    public function belongsTo(string $userId): bool
    {
        return $this->ownerId === $userId;
    }

    /** @param array<string, mixed> $filters */
    public function update(string $name, array $filters, bool $shared): void
    {
        $this->name = trim($name);
        $this->filters = $filters;
        $this->shared = $shared;
        $this->touch();
    }
}
