<?php

declare(strict_types=1);

namespace App\Pim\Enum;

/**
 * Origine d'une suggestion d'enrichissement générique (entité FicheSuggestion),
 * à arbitrer dans le bloc « Suggestions en attente ». Le châssis dessert
 * plusieurs sources gratuites : Sirene aujourd'hui (statut d'établissement,
 * backfill SIRET/TVA), Geoapify / DATAtourisme / Wikidata à venir.
 *
 * Les suggestions d'adresse BAN/Geoapify ne passent PAS par ici : elles vivent
 * en ligne sur la Localisation (banProposition/banEcart).
 */
enum SuggestionSource: string
{
    case Sirene = 'sirene';
    case Geoapify = 'geoapify';
    case DataTourisme = 'datatourisme';
    case Wikidata = 'wikidata';
    case Ia = 'ia';

    /** Libellé affiché dans le tag de la ligne de suggestion. */
    public function label(): string
    {
        return match ($this) {
            self::Sirene => 'Sirene',
            self::Geoapify => 'Geoapify',
            self::DataTourisme => 'DATAtourisme',
            self::Wikidata => 'Wikidata',
            self::Ia => 'IA',
        };
    }
}
