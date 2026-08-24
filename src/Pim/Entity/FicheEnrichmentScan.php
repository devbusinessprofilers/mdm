<?php

declare(strict_types=1);

namespace App\Pim\Entity;

use App\Pim\Enum\SuggestionSource;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Trace du dernier passage d'une source d'enrichissement sur une fiche, quel
 * que soit le résultat (une fiche scannée sans suggestion n'est pas re-scannée
 * chaque mois). Une ligne par couple (fiche, source). Le batch ne (re)scanne
 * que les fiches sans trace récente : jamais scannées, modifiées depuis le
 * scan (scanned_at < updated_at), ou scannées au-delà du seuil de fraîcheur.
 */
#[ORM\Entity(repositoryClass: \App\Pim\Repository\FicheEnrichmentScanRepository::class)]
#[ORM\Table(name: 'pim_fiche_enrichment_scan')]
class FicheEnrichmentScan
{
    #[ORM\Id]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'fiche_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Fiche $fiche;

    // Valeur de SuggestionSource, en colonne plate pour une comparaison DQL simple.
    #[ORM\Id]
    #[ORM\Column(length: 32)]
    private string $source;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $scannedAt;

    public function __construct(Fiche $fiche, SuggestionSource $source, \DateTimeImmutable $scannedAt)
    {
        $this->fiche = $fiche;
        $this->source = $source->value;
        $this->scannedAt = $scannedAt;
    }

    public function fiche(): Fiche { return $this->fiche; }
    public function source(): SuggestionSource { return SuggestionSource::from($this->source); }
    public function scannedAt(): \DateTimeImmutable { return $this->scannedAt; }
}
