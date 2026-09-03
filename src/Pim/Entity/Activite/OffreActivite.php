<?php

declare(strict_types=1);

namespace App\Pim\Entity\Activite;

use App\Pim\Enum\ModeTarificationActivite;
use App\Pim\Enum\TypeOffreActivite;
use App\Shared\Entity\TimestampableTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
#[ORM\Table(name: 'pim_activite_offre')]
#[ORM\Index(
    name: 'IDX_ACTIVITE_OFFRE_ORDERED',
    columns: ['activite_id', 'type', 'position', 'id'],
),]
#[ORM\HasLifecycleCallbacks]
class OffreActivite
{
    use TimestampableTrait;
    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    private Ulid $id;
    #[ORM\ManyToOne(inversedBy: 'offres')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Activite $activite = null;
    #[ORM\Column(length: 16, enumType: TypeOffreActivite::class)]
    private TypeOffreActivite $type = TypeOffreActivite::Forfait;
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nom = null;
    #[ORM\Column(nullable: true)]
    private ?int $participantsMin = null;
    #[ORM\Column(nullable: true)]
    private ?int $participantsMax = null;
    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    private ?string $prix = null;
    #[ORM\Column(length: 20, enumType: ModeTarificationActivite::class)]
    private ModeTarificationActivite $modeTarification = ModeTarificationActivite::Forfait;
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

    public function activite(): ?Activite
    {
        return $this->activite;
    }

    public function type(): TypeOffreActivite
    {
        return $this->type;
    }

    public function nom(): ?string
    {
        return $this->nom;
    }

    public function participantsMin(): ?int
    {
        return $this->participantsMin;
    }

    public function participantsMax(): ?int
    {
        return $this->participantsMax;
    }

    public function prix(): ?string
    {
        return $this->prix;
    }

    public function modeTarification(): ModeTarificationActivite
    {
        return $this->modeTarification;
    }

    public function position(): int
    {
        return $this->position;
    }

    public function attachTo(Activite $value): void
    {
        $this->activite = $value;
    }

    public function detach(): void
    {
        $this->activite = null;
    }

    public function changeType(TypeOffreActivite $value): void
    {
        $this->type = $value;
        if (TypeOffreActivite::Option === $value) {
            $this->modeTarification = ModeTarificationActivite::Forfait;
        }
        $this->touch();
    }

    public function changeNom(?string $value): void
    {
        $this->nom = self::normalize($value);
        $this->touch();
    }

    public function changeParticipantsMin(?int $value): void
    {
        $this->participantsMin = $value;
        $this->touch();
    }

    public function changeParticipantsMax(?int $value): void
    {
        $this->participantsMax = $value;
        $this->touch();
    }

    public function changePrix(?string $value): void
    {
        $this->prix = self::normalize($value);
        $this->touch();
    }

    public function changeModeTarification(
        ModeTarificationActivite $value,
    ): void {
        $this->modeTarification = $value;
        $this->touch();
    }

    public function changePosition(?int $value): void
    {
        $this->position = $value ?? 0;
        $this->touch();
    }

    private static function normalize(?string $value): ?string
    {
        $value = null === $value ? '' : trim($value);

        return '' === $value ? null : $value;
    }
}
