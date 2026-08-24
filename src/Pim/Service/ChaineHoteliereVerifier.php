<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Enum\SuggestionAction;
use App\Pim\Service\Wikidata\ChaineDictionnaire;

/**
 * Détecte l'affiliation d'un lieu à une chaîne / marque hôtelière à partir de
 * son nom, via le dictionnaire (référentiel interne + Wikidata). Backfill
 * seulement : ne propose rien si la chaîne est déjà renseignée.
 */
final readonly class ChaineHoteliereVerifier
{
    /** @return list<SuggestionProposee> */
    public function analyser(Lieu $lieu, ChaineDictionnaire $dictionnaire): array
    {
        if (null !== $lieu->chaineHoteliere()) {
            return [];
        }
        $label = $lieu->label();
        if (null === $label || '' === trim($label)) {
            return [];
        }
        $chaine = $dictionnaire->detecter($label);
        if (null === $chaine) {
            return [];
        }

        return [new SuggestionProposee(
            action: SuggestionAction::RemplirChamp,
            champ: 'lieu_chaine',
            label: 'Chaîne / groupe hôtelier',
            valeurActuelle: null,
            valeurProposee: $chaine,
            score: null,
        )];
    }
}
