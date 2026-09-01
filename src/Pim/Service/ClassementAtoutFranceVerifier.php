<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Enum\SuggestionAction;
use App\Pim\Lov\LieuLovCatalog;
use App\Pim\Repository\ClassementAtoutFranceRepository;

/**
 * Confronte un lieu au classement officiel Atout France (référentiel local,
 * aucun appel réseau) pour proposer la typologie dérivée des étoiles et le
 * nombre de chambres — la source autoritative pour les hébergements français,
 * là où le tag OSM `stars` est déclaratif. Le fichier ne portant pas de SIRET,
 * le rapprochement se fait par nom (NomSimilarite) borné au code postal ; le
 * score de similarité accompagne la suggestion. Backfill seulement, France
 * uniquement.
 */
final readonly class ClassementAtoutFranceVerifier
{
    /** Étoiles → code LOV GENERALE_TYPOLOGIE (une étoile n'a pas d'équivalent dans la liste). */
    private const TYPOLOGIE_HOTEL = [
        2 => 'GENERALE_TYPOLOGIE_1',
        3 => 'GENERALE_TYPOLOGIE_2',
        4 => 'GENERALE_TYPOLOGIE_3',
        5 => 'GENERALE_TYPOLOGIE_4',
    ];

    /** Types non hôteliers du fichier dont la typologie est sans ambiguïté. */
    private const TYPOLOGIE_TYPE = [
        'RÉSIDENCE DE TOURISME' => 'GENERALE_TYPOLOGIE_42',
        'CAMPING' => 'GENERALE_TYPOLOGIE_33',
        'VILLAGE DE VACANCES' => 'GENERALE_TYPOLOGIE_33',
    ];

    public function __construct(private ClassementAtoutFranceRepository $classements)
    {
    }

    /** @return list<SuggestionProposee> */
    public function analyser(Lieu $lieu): array
    {
        $localisation = $lieu->localisation();
        if (null === $localisation || !LocalisationBanVerifier::estFrancaise($localisation)) {
            return [];
        }
        $codePostal = trim((string) $localisation->codePostal());
        $label = trim((string) $lieu->fiche()->label());
        if ('' === $codePostal || '' === $label) {
            return [];
        }
        $meilleur = null;
        $meilleurScore = 0.0;
        foreach ($this->classements->parCodePostal($codePostal) as $candidat) {
            $score = NomSimilarite::score($label, $candidat['nom']);
            if ($score > $meilleurScore) {
                [$meilleur, $meilleurScore] = [$candidat, $score];
            }
        }
        if (null === $meilleur || $meilleurScore < NomSimilarite::SEUIL_DEFAUT) {
            return [];
        }
        $propositions = [];

        $code = 'HÔTEL DE TOURISME' === $meilleur['typeEtablissement']
            ? (self::TYPOLOGIE_HOTEL[$meilleur['etoiles']] ?? null)
            : (self::TYPOLOGIE_TYPE[$meilleur['typeEtablissement']] ?? null);
        // Garde référentiel : le code doit exister dans la liste effective.
        $code = null === $code ? null : LovValeurResolution::codePour(LieuLovCatalog::choicesFor('GENERALE_TYPOLOGIE'), $code);
        if (null !== $code && [] === $lieu->generaleTypologie()) {
            $propositions[] = new SuggestionProposee(
                action: SuggestionAction::RemplirChamp,
                champ: 'lieu_lov_typologie',
                label: 'Typologie',
                valeurActuelle: null,
                valeurProposee: LieuLovCatalog::choicesFor('GENERALE_TYPOLOGIE')[$code] ?? $code,
                score: $meilleurScore,
                payload: ['attribut' => 'GENERALE_TYPOLOGIE', 'codes' => [$code]],
            );
        }
        if (null !== $meilleur['nombreChambres'] && $meilleur['nombreChambres'] > 0 && null === $lieu->chambreNbTotal()) {
            $propositions[] = new SuggestionProposee(
                action: SuggestionAction::RemplirChamp,
                champ: 'lieu_chambre_nb_total',
                label: 'Nombre total de chambres',
                valeurActuelle: null,
                valeurProposee: (string) $meilleur['nombreChambres'],
                score: $meilleurScore,
                payload: ['int' => $meilleur['nombreChambres']],
            );
        }

        return $propositions;
    }
}
