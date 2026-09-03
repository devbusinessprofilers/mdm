<?php

declare(strict_types=1);

namespace App\Pim\Entity\Lieu;

use App\Shared\Entity\TimestampableTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
#[ORM\Table(name: 'pim_periode_fermeture')]
#[ORM\Index(name: 'IDX_PIM_CLOSURE_ORDERED', columns: ['lieu_id', 'date_debut', 'id'])]
#[ORM\HasLifecycleCallbacks]
class PeriodeFermeture
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    private Ulid $id;

    #[ORM\ManyToOne(inversedBy: 'periodesFermeture')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Lieu $lieu = null;

    #[ORM\Column(length: 255)]
    private string $nom = '';

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $dateDebut = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $dateFin = null;

    public function __construct()
    {
        $this->id = new Ulid();
        $this->initializeTimestamps();
    }

    public function id(): string
    {
        return (string) $this->id;
    }

    public function lieu(): ?Lieu
    {
        return $this->lieu;
    }

    public function nom(): string
    {
        return $this->nom;
    }

    public function dateDebut(): ?\DateTimeImmutable
    {
        return $this->dateDebut;
    }

    public function dateFin(): ?\DateTimeImmutable
    {
        return $this->dateFin;
    }

    public function changeNom(string $value): void
    {
        $this->nom = trim($value);
        $this->touch();
    }

    public function changeDateDebut(?\DateTimeImmutable $value): void
    {
        $this->dateDebut = $value;
        $this->touch();
    }

    public function changeDateFin(?\DateTimeImmutable $value): void
    {
        $this->dateFin = $value;
        $this->touch();
    }

    public function attachTo(Lieu $lieu): void
    {
        $this->lieu = $lieu;
    }

    public function detachFrom(Lieu $lieu): void
    {
        if ($this->lieu === $lieu) {
            $this->lieu = null;
        }
    }
}
