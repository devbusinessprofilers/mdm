<?php

declare(strict_types=1);

namespace App\Pim\Entity\Lieu;

use App\Pim\Enum\NatureRessource;
use App\Shared\Entity\TimestampableTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
#[ORM\Table(name: 'pim_ressource_lieu')]
#[ORM\Index(name: 'IDX_PIM_RESOURCE_LIEU_ORDERED', columns: ['lieu_id', 'position', 'id'])]
#[ORM\Index(name: 'IDX_PIM_RESOURCE_SALLE_ORDERED', columns: ['salle_id', 'position', 'id'])]
#[ORM\Index(name: 'IDX_PIM_RESOURCE_USAGE', columns: ['usage_code', 'lieu_id'])]
#[ORM\HasLifecycleCallbacks]
class RessourceLieu
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    private Ulid $id;

    #[ORM\ManyToOne(inversedBy: 'ressources')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Lieu $lieu = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Salle $salle = null;

    #[ORM\Column(length: 255)]
    private string $damAssetId = '';

    #[ORM\Column(length: 32, enumType: NatureRessource::class)]
    private NatureRessource $nature = NatureRessource::Document;

    #[ORM\Column(name: 'usage_code', length: 64)]
    private string $usage = '';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $legende = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $position = 0;

    public function __construct()
    {
        $this->id = new Ulid();
        $this->initializeTimestamps();
    }

    public function id(): string { return (string) $this->id; }
    public function lieu(): ?Lieu { return $this->lieu; }
    public function salle(): ?Salle { return $this->salle; }
    public function damAssetId(): string { return $this->damAssetId; }
    public function nature(): NatureRessource { return $this->nature; }
    public function usage(): string { return $this->usage; }
    public function legende(): ?string { return $this->legende; }
    public function position(): int { return $this->position; }
    public function changeSalle(?Salle $value): void { $this->salle = $value; $this->touch(); }
    public function changeDamAssetId(string $value): void { $this->damAssetId = trim($value); $this->touch(); }
    public function changeNature(NatureRessource $value): void { $this->nature = $value; $this->touch(); }
    public function changeUsage(string $value): void { $this->usage = trim($value); $this->touch(); }
    public function changeLegende(?string $value): void { $this->legende = null === $value || '' === trim($value) ? null : trim($value); $this->touch(); }
    public function changePosition(?int $value): void { $this->position = $value ?? 0; $this->touch(); }
    public function attachTo(Lieu $lieu): void { $this->lieu = $lieu; }
    public function detachFrom(Lieu $lieu): void { if ($this->lieu === $lieu) { $this->lieu = null; } }
}
