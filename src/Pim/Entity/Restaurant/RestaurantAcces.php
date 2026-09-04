<?php

declare(strict_types=1);

namespace App\Pim\Entity\Restaurant;

use App\Pim\Enum\TypeAccesRestaurant;
use App\Shared\Entity\TimestampableTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
#[ORM\Table(name: 'pim_restaurant_acces')]
#[ORM\Index(name: 'IDX_RESTAURANT_ACCES_ORDERED', columns: ['restaurant_id', 'type', 'position', 'id'])]
#[ORM\HasLifecycleCallbacks]
class RestaurantAcces
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    private Ulid $id;

    #[ORM\ManyToOne(inversedBy: 'acces')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Restaurant $restaurant = null;

    #[ORM\Column(length: 24, enumType: TypeAccesRestaurant::class)]
    private TypeAccesRestaurant $type = TypeAccesRestaurant::Gare;

    #[ORM\Column(length: 255)]
    private string $nom = '';

    // Comme AccesLieu : distance, durée et mode de transport (maquette).
    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2, nullable: true)]
    private ?string $distanceKilometres = null;

    #[ORM\Column(nullable: true)]
    private ?int $dureeMinutes = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $modeTransport = null;

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

    public function restaurant(): ?Restaurant
    {
        return $this->restaurant;
    }

    public function type(): TypeAccesRestaurant
    {
        return $this->type;
    }

    public function nom(): string
    {
        return $this->nom;
    }

    public function position(): int
    {
        return $this->position;
    }

    public function distanceKilometres(): ?string
    {
        return $this->distanceKilometres;
    }

    public function dureeMinutes(): ?int
    {
        return $this->dureeMinutes;
    }

    public function modeTransport(): ?string
    {
        return $this->modeTransport;
    }

    public function changeDistanceKilometres(?string $value): void
    {
        $this->distanceKilometres = null === $value || '' === trim($value) ? null : trim($value);
        $this->touch();
    }

    public function changeDureeMinutes(?int $value): void
    {
        $this->dureeMinutes = $value;
        $this->touch();
    }

    public function changeModeTransport(?string $value): void
    {
        $this->modeTransport = null === $value || '' === trim($value) ? null : trim($value);
        $this->touch();
    }

    public function changeType(TypeAccesRestaurant $value): void
    {
        $this->type = $value;
        $this->touch();
    }

    public function changeNom(string $value): void
    {
        $this->nom = trim($value);
        $this->touch();
    }

    public function changePosition(?int $value): void
    {
        $this->position = $value ?? 0;
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
