<?php

declare(strict_types=1);

namespace App\Pim\Entity\Lieu;

use App\Shared\Entity\TimestampableTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
#[ORM\Table(name: 'pim_salle')]
#[ORM\Index(name: 'IDX_PIM_SALLE_ORDERED', columns: ['lieu_id', 'position', 'id'])]
#[ORM\HasLifecycleCallbacks]
class Salle
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    private Ulid $id;

    #[ORM\ManyToOne(inversedBy: 'salles')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Lieu $lieu = null;

    #[ORM\Column(length: 255)]
    private string $nom = '';

    #[ORM\Column(nullable: true)]
    private ?int $superficie = null;

    #[ORM\Column(nullable: true)]
    private ?int $capaciteReunion = null;

    #[ORM\Column(nullable: true)]
    private ?int $capaciteU = null;

    #[ORM\Column(nullable: true)]
    private ?int $capaciteClasse = null;

    #[ORM\Column(nullable: true)]
    private ?int $capaciteTheatre = null;

    #[ORM\Column(nullable: true)]
    private ?int $capaciteCabaret = null;

    #[ORM\Column(nullable: true)]
    private ?int $capaciteBanquet = null;

    #[ORM\Column(nullable: true)]
    private ?int $capaciteCocktail = null;

    #[ORM\Column(nullable: true)]
    private ?int $capaciteAuditorium = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $lumiereJour = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $accesPmr = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $espaceDansant = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $climatisee = false;

    #[ORM\Column(options: ['default' => 0])]
    private int $position = 0;

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

    public function superficie(): ?int
    {
        return $this->superficie;
    }

    public function capaciteReunion(): ?int
    {
        return $this->capaciteReunion;
    }

    public function capaciteU(): ?int
    {
        return $this->capaciteU;
    }

    public function capaciteClasse(): ?int
    {
        return $this->capaciteClasse;
    }

    public function capaciteTheatre(): ?int
    {
        return $this->capaciteTheatre;
    }

    public function capaciteCabaret(): ?int
    {
        return $this->capaciteCabaret;
    }

    public function capaciteBanquet(): ?int
    {
        return $this->capaciteBanquet;
    }

    public function capaciteCocktail(): ?int
    {
        return $this->capaciteCocktail;
    }

    public function capaciteAuditorium(): ?int
    {
        return $this->capaciteAuditorium;
    }

    public function lumiereJour(): bool
    {
        return $this->lumiereJour;
    }

    public function accesPmr(): bool
    {
        return $this->accesPmr;
    }

    public function espaceDansant(): bool
    {
        return $this->espaceDansant;
    }

    public function climatisee(): bool
    {
        return $this->climatisee;
    }

    public function position(): int
    {
        return $this->position;
    }

    public function changeNom(string $value): void
    {
        $this->nom = trim($value);
        $this->touch();
    }

    public function changeSuperficie(?int $value): void
    {
        $this->superficie = $value;
        $this->touch();
    }

    public function changeCapaciteReunion(?int $value): void
    {
        $this->capaciteReunion = $value;
        $this->touch();
    }

    public function changeCapaciteU(?int $value): void
    {
        $this->capaciteU = $value;
        $this->touch();
    }

    public function changeCapaciteClasse(?int $value): void
    {
        $this->capaciteClasse = $value;
        $this->touch();
    }

    public function changeCapaciteTheatre(?int $value): void
    {
        $this->capaciteTheatre = $value;
        $this->touch();
    }

    public function changeCapaciteCabaret(?int $value): void
    {
        $this->capaciteCabaret = $value;
        $this->touch();
    }

    public function changeCapaciteBanquet(?int $value): void
    {
        $this->capaciteBanquet = $value;
        $this->touch();
    }

    public function changeCapaciteCocktail(?int $value): void
    {
        $this->capaciteCocktail = $value;
        $this->touch();
    }

    public function changeCapaciteAuditorium(?int $value): void
    {
        $this->capaciteAuditorium = $value;
        $this->touch();
    }

    public function changeLumiereJour(bool $value): void
    {
        $this->lumiereJour = $value;
        $this->touch();
    }

    public function changeAccesPmr(bool $value): void
    {
        $this->accesPmr = $value;
        $this->touch();
    }

    public function changeEspaceDansant(bool $value): void
    {
        $this->espaceDansant = $value;
        $this->touch();
    }

    public function changeClimatisee(bool $value): void
    {
        $this->climatisee = $value;
        $this->touch();
    }

    public function changePosition(?int $value): void
    {
        $this->position = $value ?? 0;
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
