<?php

declare(strict_types=1);

namespace App\Pim\Entity\Restaurant;

use App\Shared\Entity\TimestampableTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
#[ORM\Table(name: 'pim_restaurant_periode_fermeture')]
#[ORM\Index(name: 'IDX_RESTAURANT_CLOSURE_ORDERED', columns: ['restaurant_id', 'date_debut', 'id'])]
#[ORM\HasLifecycleCallbacks]
class RestaurantPeriodeFermeture
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    private Ulid $id;

    #[ORM\ManyToOne(inversedBy: 'periodesFermeture')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Restaurant $restaurant = null;

    #[ORM\Column(length: 255)]
    private string $nom = '';

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dateDebut = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
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

    public function restaurant(): ?Restaurant
    {
        return $this->restaurant;
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

    public function attachTo(Restaurant $restaurant): void
    {
        $this->restaurant = $restaurant;
    }

    public function detachFrom(Restaurant $restaurant): void
    {
        if ($this->restaurant === $restaurant) {
            $this->restaurant = null;
        }
    }
}
