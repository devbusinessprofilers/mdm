<?php

declare(strict_types=1);

namespace App\Pim\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

/**
 * Trace d'un traitement d'attribution géographique de visibilité (CDC §10.1),
 * pour le journal des traitements /outils : rattrapage global par la commande,
 * attribution à la création d'une fiche, ou clic « Appliquer les sites
 * automatiques ». Tout traitement laisse son historique, même quand il n'a
 * rien ajouté — l'absence d'effet se lit sinon comme une absence de passage.
 */
#[ORM\Entity]
#[ORM\Table(name: 'pim_visibilite_geo_run')]
#[ORM\Index(name: 'IDX_VISIBILITE_GEO_RUN_EXECUTED', columns: ['executed_at'])]
#[ORM\Index(name: 'IDX_VISIBILITE_GEO_RUN_FICHE', columns: ['fiche_id'])]
class VisibiliteGeoRun
{
    public const DECLENCHEUR_COMMANDE = 'commande';
    public const DECLENCHEUR_CREATION = 'creation';
    public const DECLENCHEUR_BOUTON = 'bouton';

    #[ORM\Id]
    #[ORM\Column(type: 'ulid')]
    private Ulid $id;

    /** Nulle pour le rattrapage global ; l'historique survit à la fiche. */
    #[ORM\ManyToOne(targetEntity: Fiche::class)]
    #[ORM\JoinColumn(name: 'fiche_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Fiche $fiche;

    #[ORM\Column(length: 16)]
    private string $declencheur;

    #[ORM\Column(name: 'nb_fiches', options: ['unsigned' => true])]
    private int $nbFiches;

    #[ORM\Column(name: 'nb_attributions', options: ['unsigned' => true])]
    private int $nbAttributions;

    /** @var array<string, int>|null attributions par code de site (rattrapage global) */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $detail;

    #[ORM\Column(name: 'executed_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $executedAt;

    /** @param array<string, int>|null $detail */
    public function __construct(string $declencheur, ?Fiche $fiche, int $nbFiches, int $nbAttributions, ?array $detail = null)
    {
        $this->id = new Ulid();
        $this->declencheur = $declencheur;
        $this->fiche = $fiche;
        $this->nbFiches = $nbFiches;
        $this->nbAttributions = $nbAttributions;
        $this->detail = $detail;
        $this->executedAt = new \DateTimeImmutable();
    }

    public function idString(): string
    {
        return (string) $this->id;
    }

    public function declencheur(): string
    {
        return $this->declencheur;
    }

    public function nbAttributions(): int
    {
        return $this->nbAttributions;
    }
}
