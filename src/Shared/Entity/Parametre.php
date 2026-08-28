<?php

declare(strict_types=1);

namespace App\Shared\Entity;

use App\Shared\Enum\TypeParametre;
use App\Shared\Repository\ParametreRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

/**
 * Réglage métier modifiable dans /admin sans redéploiement. La valeur stockée
 * est toujours une chaîne (castée selon `type` à la lecture) ; NULL signifie
 * « non surchargé » et la variable d'environnement reste la valeur effective.
 */
#[ORM\Entity(repositoryClass: ParametreRepository::class)]
#[ORM\Table(name: 'parametre')]
#[ORM\UniqueConstraint(name: 'UNIQ_PARAMETRE_NOM', columns: ['nom'])]
class Parametre
{
    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    private Ulid $id;

    #[ORM\Column(length: 64)]
    private string $nom;

    #[ORM\Column(length: 255)]
    private string $description;

    #[ORM\Column(length: 8, enumType: TypeParametre::class)]
    private TypeParametre $type;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $valeur = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct(string $nom, string $description, TypeParametre $type)
    {
        $this->id = new Ulid();
        $this->nom = $nom;
        $this->description = $description;
        $this->type = $type;
    }

    public function id(): string
    {
        return (string) $this->id;
    }

    public function nom(): string
    {
        return $this->nom;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function type(): TypeParametre
    {
        return $this->type;
    }

    public function valeur(): ?string
    {
        return $this->valeur;
    }

    public function updatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function estSurcharge(): bool
    {
        return null !== $this->valeur;
    }

    public function surcharger(string $valeur): void
    {
        $this->valeur = $valeur;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function revenirAuDefaut(): void
    {
        $this->valeur = null;
        $this->updatedAt = new \DateTimeImmutable();
    }
}
