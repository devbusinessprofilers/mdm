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
        // Backfill seulement : le sélecteur LOV « Groupe et chaîne hôtelière »
        // est l'unique champ chaîne de la fiche.
        if ([] !== $lieu->generaleChainesGroupeHot()) {
            return [];
        }
        $label = $lieu->label();
        if (null === $label || '' === trim($label)) {
            return [];
        }
        $detection = $dictionnaire->detecter($label);
        if (null === $detection) {
            return [];
        }

        // L'enseigne (« Mercure ») est proposée — elle correspond aux marques
        // de la LOV — le groupe (« Accor ») reste en information dans le payload.
        return [new SuggestionProposee(
            action: SuggestionAction::RemplirChamp,
            champ: 'lieu_chaine',
            label: 'Chaîne / groupe hôtelier',
            valeurActuelle: null,
            valeurProposee: $detection->enseigne,
            score: null,
            payload: $detection->enseigne === $detection->groupe ? null : ['groupe' => $detection->groupe],
        )];
    }
}
