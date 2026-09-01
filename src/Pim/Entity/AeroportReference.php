<?php

declare(strict_types=1);

namespace App\Pim\Entity;

use App\Pim\Repository\AeroportReferenceRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Référentiel statique mondial des aéroports à trafic commercial régulier
 * (source OurAirports, domaine public), pour les suggestions du bloc Accès.
 * Rechargé en bloc par app:acces:importer-aeroports — les lignes ne sont
 * jamais modifiées individuellement, d'où l'absence de mutateurs.
 */
#[ORM\Entity(repositoryClass: AeroportReferenceRepository::class)]
#[ORM\Table(name: 'pim_aeroport_reference')]
#[ORM\Index(name: 'IDX_PIM_AEROPORT_REF_GEO', columns: ['latitude', 'longitude'])]
class AeroportReference
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\Column(length: 255)]
        private string $nom,
        #[ORM\Column(length: 3, nullable: true)]
        private ?string $codeIata,
        #[ORM\Column(length: 2)]
        private string $codePays,
        #[ORM\Column]
        private float $latitude,
        #[ORM\Column]
        private float $longitude,
    ) {
    }

    public function id(): ?int { return $this->id; }
    public function nom(): string { return $this->nom; }
    public function codeIata(): ?string { return $this->codeIata; }
    public function codePays(): string { return $this->codePays; }
    public function latitude(): float { return $this->latitude; }
    public function longitude(): float { return $this->longitude; }
}
