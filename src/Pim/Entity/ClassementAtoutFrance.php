<?php

declare(strict_types=1);

namespace App\Pim\Entity;

use App\Pim\Repository\ClassementAtoutFranceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Référentiel statique du classement officiel des hébergements touristiques
 * français (open data Atout France, Licence Ouverte) : la source autoritative
 * des étoiles, pour les suggestions de typologie et de nombre de chambres.
 * Le fichier ne porte pas de SIRET : le rapprochement se fait par nom et code
 * postal. Rechargé en bloc par app:pim:importer-classements-atout-france —
 * les lignes ne sont jamais modifiées individuellement, d'où l'absence de
 * mutateurs.
 */
#[ORM\Entity(repositoryClass: ClassementAtoutFranceRepository::class)]
#[ORM\Table(name: 'pim_classement_atout_france')]
#[ORM\Index(name: 'IDX_PIM_CLASSEMENT_AF_CP', columns: ['code_postal'])]
class ClassementAtoutFrance
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\Column(length: 255)]
        private string $nom,
        #[ORM\Column(length: 8)]
        private string $codePostal,
        #[ORM\Column(length: 255)]
        private string $commune,
        #[ORM\Column(length: 64)]
        private string $typeEtablissement,
        #[ORM\Column]
        private int $etoiles,
        #[ORM\Column(nullable: true)]
        private ?int $nombreChambres,
        #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
        private ?\DateTimeImmutable $dateClassement,
    ) {
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function nom(): string
    {
        return $this->nom;
    }

    public function codePostal(): string
    {
        return $this->codePostal;
    }

    public function commune(): string
    {
        return $this->commune;
    }

    public function typeEtablissement(): string
    {
        return $this->typeEtablissement;
    }

    public function etoiles(): int
    {
        return $this->etoiles;
    }

    public function nombreChambres(): ?int
    {
        return $this->nombreChambres;
    }

    public function dateClassement(): ?\DateTimeImmutable
    {
        return $this->dateClassement;
    }
}
