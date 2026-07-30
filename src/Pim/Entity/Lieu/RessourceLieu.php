<?php

declare(strict_types=1);

namespace App\Pim\Entity\Lieu;

use App\Pim\Enum\NatureRessource;
use App\Pim\Repository\RessourceLieuRepository;
use App\Shared\Entity\TimestampableTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: RessourceLieuRepository::class)]
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

    #[ORM\Column(options: ['default' => false])]
    private bool $rightsGranted = false;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $rightsGrantedAt = null;

    #[ORM\Column(length: 26, nullable: true)]
    private ?string $rightsGrantedBy = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $source = null;

    #[ORM\Column(nullable: true)]
    private ?int $cropX = null;

    #[ORM\Column(nullable: true)]
    private ?int $cropY = null;

    #[ORM\Column(nullable: true)]
    private ?int $cropWidth = null;

    #[ORM\Column(nullable: true)]
    private ?int $cropHeight = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $rotation = 0;

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
    public function rightsGranted(): bool { return $this->rightsGranted; }
    public function rightsGrantedAt(): ?\DateTimeImmutable { return $this->rightsGrantedAt; }
    public function rightsGrantedBy(): ?string { return $this->rightsGrantedBy; }
    public function source(): ?string { return $this->source; }
    public function rotation(): int { return $this->rotation; }
    /** @return array{x: int, y: int, width: int, height: int}|null */
    public function crop(): ?array
    {
        if (null === $this->cropX || null === $this->cropY || null === $this->cropWidth || null === $this->cropHeight) {
            return null;
        }

        return ['x' => $this->cropX, 'y' => $this->cropY, 'width' => $this->cropWidth, 'height' => $this->cropHeight];
    }
    public function changeSalle(?Salle $value): void { $this->salle = $value; $this->touch(); }
    public function changeDamAssetId(?string $value): void { $this->damAssetId = trim((string) $value); $this->touch(); }
    public function changeNature(NatureRessource $value): void { $this->nature = $value; $this->touch(); }
    public function changeUsage(string $value): void { $this->usage = trim($value); $this->touch(); }
    public function changeLegende(?string $value): void { $this->legende = null === $value || '' === trim($value) ? null : trim($value); $this->touch(); }
    public function changePosition(?int $value): void { $this->position = $value ?? 0; $this->touch(); }
    public function changeSource(?string $value): void { $this->source = null === $value || '' === trim($value) ? null : trim($value); $this->touch(); }
    public function grantRights(string $userId): void { $this->rightsGranted = true; $this->rightsGrantedAt = new \DateTimeImmutable(); $this->rightsGrantedBy = $userId; $this->touch(); }
    public function revokeRights(): void { $this->rightsGranted = false; $this->rightsGrantedAt = null; $this->rightsGrantedBy = null; $this->touch(); }
    public function changeCrop(?int $x, ?int $y, ?int $width, ?int $height): void
    {
        if ((null !== $width && $width <= 0) || (null !== $height && $height <= 0) || (null !== $x && $x < 0) || (null !== $y && $y < 0)) {
            throw new \DomainException('Les coordonnées de recadrage sont invalides.');
        }
        $values = [$x, $y, $width, $height];
        if (0 !== count(array_filter($values, static fn (?int $value): bool => null !== $value)) && 4 !== count(array_filter($values, static fn (?int $value): bool => null !== $value))) {
            throw new \DomainException('Toutes les coordonnées de recadrage sont requises.');
        }
        [$this->cropX, $this->cropY, $this->cropWidth, $this->cropHeight] = $values;
        $this->touch();
    }
    public function changeRotation(int $rotation): void
    {
        if (!in_array($rotation, [0, 90, 180, 270], true)) {
            throw new \DomainException('La rotation doit valoir 0, 90, 180 ou 270 degrés.');
        }
        $this->rotation = $rotation;
        $this->touch();
    }
    public function attachTo(Lieu $lieu): void { $this->lieu = $lieu; }
    public function detachFrom(Lieu $lieu): void { if ($this->lieu === $lieu) { $this->lieu = null; } }
}
