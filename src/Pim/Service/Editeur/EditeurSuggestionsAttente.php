<?php

declare(strict_types=1);

namespace App\Pim\Service\Editeur;

use App\Pim\Entity\Fiche;
use App\Pim\Form\AdresseSuggestionFormFactory;
use App\Pim\Form\EnrichissementSuggestionFormFactory;
use App\Pim\Repository\FicheSuggestionRepository;
use App\Pim\Service\ChaineLovResolution;
use App\Pim\Service\LocalisationBanVerifier;

/**
 * Bloc « Suggestions en attente » de l'onglet Informations générales :
 * corrections et enrichissements proposés par les vérifications
 * automatiques, une suggestion par ligne avec sa source (BAN, Geoapify,
 * Sirene…), à arbitrer en un clic. Les suggestions de l'extraction OCR ne
 * passent pas ici : elles vivent dans le flux d'extraction.
 */
final readonly class EditeurSuggestionsAttente
{
    public function __construct(
        private AdresseSuggestionFormFactory $adresseSuggestions,
        private FicheSuggestionRepository $suggestions,
        private EnrichissementSuggestionFormFactory $enrichissementSuggestions,
    ) {
    }

    /** @return array{lignes: list<array<string, mixed>>} */
    public function variables(Fiche $fiche): array
    {
        $lignes = [];
        $localisation = $fiche->localisation();
        if (null !== $localisation && $localisation->banEcart()) {
            $proposition = $localisation->banProposition();
            $lignes[] = [
                'source' => LocalisationBanVerifier::estFrancaise($localisation) ? 'BAN' : 'Geoapify',
                'label' => 'Adresse',
                'actuel' => trim(sprintf(
                    '%s %s %s',
                    $localisation->ruePostale() ?? '',
                    $localisation->codePostal() ?? '',
                    $localisation->ville() ?? '',
                )),
                'valeur' => self::propositionAffichable($proposition),
                'confiance' => null === $localisation->banScore() ? null : (int) round($localisation->banScore() * 100),
                // Sans proposition (aucun résultat fiable), il n'y a rien à
                // accepter : la correction se fait dans la section Localisation.
                'accepter' => null === $proposition
                    ? null
                    : $this->adresseSuggestions->action($fiche->idString(), 'accepter')->createView(),
                'ignorer' => $this->adresseSuggestions->action($fiche->idString(), 'ignorer')->createView(),
            ];
        }
        // Suggestions génériques (Sirene : établissement cessé, backfill SIRET/TVA ;
        // Geoapify/DATAtourisme/Wikidata) — même gabarit de ligne.
        foreach ($this->suggestions->findEnAttentePourFiche($fiche) as $suggestion) {
            $lignes[] = [
                'source' => $suggestion->source()->label(),
                'label' => $suggestion->label(),
                'actuel' => $suggestion->valeurActuelle() ?? '',
                'valeur' => $suggestion->valeurProposee() ?? '',
                // Enseigne absente de la LOV : accepter créera l'entrée — le dire.
                'note' => 'lieu_chaine' === $suggestion->champ() && ChaineLovResolution::creeraUneEntree($suggestion->valeurProposee())
                    ? 'Créera une nouvelle entrée dans la liste « Groupe et Chaîne hôtelière ».'
                    : null,
                'confiance' => null === $suggestion->score() ? null : (int) round($suggestion->score() * 100),
                'accepter' => $this->enrichissementSuggestions->action($suggestion->id(), 'accepter')->createView(),
                'ignorer' => $this->enrichissementSuggestions->action($suggestion->id(), 'ignorer')->createView(),
            ];
        }

        return ['lignes' => $lignes];
    }

    /** @param array{label?: ?string, name?: ?string, codePostal?: ?string, ville?: ?string}|null $proposition */
    private static function propositionAffichable(?array $proposition): string
    {
        if (null === $proposition) {
            return 'Aucun résultat fiable dans la Base Adresse Nationale — adresse à vérifier manuellement.';
        }
        $label = $proposition['label'] ?? null;
        if (null !== $label && '' !== $label) {
            return $label;
        }

        return trim(sprintf(
            '%s %s %s',
            $proposition['name'] ?? '',
            $proposition['codePostal'] ?? '',
            $proposition['ville'] ?? '',
        ));
    }
}
