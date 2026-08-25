<?php

declare(strict_types=1);

namespace App\Pim\Entity;

use App\Pim\Repository\FicheEnrichmentRunRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

/**
 * Demande d'enrichissement d'une fiche (bouton « Enrichir ce qui manque ») :
 * créée au clic (visible « en file » dans le journal /outils), complétée par le
 * worker avec le résultat par source — nombre de suggestions, source inactive
 * (gate off) ou API indisponible. C'est la trace lisible de ce que le scan a
 * réellement fait, là où pim_fiche_enrichment_scan ne dit que « déjà scanné ».
 */
#[ORM\Entity(repositoryClass: FicheEnrichmentRunRepository::class)]
#[ORM\Table(name: 'pim_fiche_enrichment_run')]
#[ORM\Index(name: 'IDX_FICHE_ENRICHMENT_RUN_REQUESTED', columns: ['requested_at'])]
#[ORM\Index(name: 'IDX_FICHE_ENRICHMENT_RUN_FICHE', columns: ['fiche_id'])]
class FicheEnrichmentRun
{
    #[ORM\Id]
    #[ORM\Column(type: 'ulid')]
    private Ulid $id;

    #[ORM\ManyToOne(targetEntity: Fiche::class)]
    #[ORM\JoinColumn(name: 'fiche_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Fiche $fiche;

    #[ORM\Column(name: 'requested_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $requestedAt;

    #[ORM\Column(name: 'finished_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $finishedAt = null;

    /** @var array<string, int|string>|null résultat par source (n suggestions, 'inactif', 'indisponible'…) */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $resultat = null;

    public function __construct(Fiche $fiche)
    {
        $this->id = new Ulid();
        $this->fiche = $fiche;
        $this->requestedAt = new \DateTimeImmutable();
    }

    public function idString(): string
    {
        return (string) $this->id;
    }

    public function fiche(): Fiche
    {
        return $this->fiche;
    }

    public function estTerminee(): bool
    {
        return null !== $this->finishedAt;
    }

    /** @return array<string, int|string>|null */
    public function resultat(): ?array
    {
        return $this->resultat;
    }

    /** @param array<string, int|string> $resultat */
    public function terminer(array $resultat): void
    {
        $this->finishedAt = new \DateTimeImmutable();
        $this->resultat = $resultat;
    }
}
