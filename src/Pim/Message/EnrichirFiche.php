<?php

declare(strict_types=1);

namespace App\Pim\Message;

/**
 * Enrichissement à la demande d'une fiche (bouton « Enrichir ce qui manque ») :
 * toutes les sources applicables et actives (Sirene, Geoapify, DATAtourisme,
 * Wikidata, BAN, IA) sont passées sur cette seule fiche, en asynchrone — le
 * scan DATAtourisme lit le flux entier et l'IA appelle OpenAI.
 */
final readonly class EnrichirFiche
{
    /** @param ?string $runId trace FicheEnrichmentRun créée au clic (journal /outils) */
    public function __construct(public string $ficheId, public ?string $runId = null)
    {
    }
}
