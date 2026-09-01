<?php

declare(strict_types=1);

namespace App\Pim\Entity;

use App\Pim\Repository\GrandeVilleReferenceRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Référentiel statique mondial des villes de 15 000 habitants et plus
 * (source GeoNames cities15000, CC-BY), pour les suggestions du bloc Accès.
 * Rechargé en bloc par app:acces:importer-grandes-villes — les lignes ne
 * sont jamais modifiées individuellement, d'où l'absence de mutateurs.
 */
#[ORM\Entity(repositoryClass: GrandeVilleReferenceRepository::class)]
#[ORM\Table(name: 'pim_grande_ville_reference')]
#[ORM\Index(name: 'IDX_PIM_GRANDE_VILLE_REF_GEO', columns: ['latitude', 'longitude'])]
class GrandeVilleReference
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\Column(length: 255)]
        private string $nom,
        #[ORM\Column(length: 2)]
        private string $codePays,
        #[ORM\Column]
        private int $population,
        #[ORM\Column]
        private float $latitude,
        #[ORM\Column]
        private float $longitude,
    ) {
    }

    public function id(): ?int { return $this->id; }
    public function nom(): string { return $this->nom; }
    public function codePays(): string { return $this->codePays; }
    public function population(): int { return $this->population; }
    public function latitude(): float { return $this->latitude; }
    public function longitude(): float { return $this->longitude; }
}
