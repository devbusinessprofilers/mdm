<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Enum\SuggestionAction;
use App\Pim\Lov\LieuLovCatalog;

/**
 * Confronte un lieu (hôtel, salle…) à Geoapify Place Details (tags
 * OpenStreetMap) pour proposer des attributs manquants, sans rien persister :
 * classement en étoiles (→ typologie), enseigne (→ chaîne / groupe hôtelier)
 * et site web. Backfill seulement : on ne propose que des valeurs absentes de
 * la fiche. Tous pays dès qu'un GPS est présent.
 */
final readonly class LieuAttributsVerifier
{
    /** OSM `stars` → code LOV GENERALE_TYPOLOGIE (une étoile n'a pas d'équivalent dans la liste). */
    private const ETOILES = [
        2 => 'GENERALE_TYPOLOGIE_1',
        3 => 'GENERALE_TYPOLOGIE_2',
        4 => 'GENERALE_TYPOLOGIE_3',
        5 => 'GENERALE_TYPOLOGIE_4',
    ];

    public function __construct(private GeoapifyClient $geoapify)
    {
    }

    /**
     * @return list<SuggestionProposee>
     *
     * @throws EnrichissementIndisponibleException quand Geoapify est en panne
     *                                             ou sous quota
     */
    public function analyser(Lieu $lieu): array
    {
        $localisation = $lieu->localisation();
        if (null === $localisation || null === $localisation->latitude() || null === $localisation->longitude()) {
            return [];
        }
        // Le nom de la fiche sert de contre-vérification : sans lui, un GPS
        // imprécis ferait remonter les attributs du commerce voisin.
        $nom = trim((string) $lieu->fiche()->label());
        $attributs = $this->geoapify->detailsPlace($localisation->latitude(), $localisation->longitude(), '' === $nom ? null : $nom);
        if (null === $attributs || $attributs->estVide()) {
            return [];
        }
        $propositions = [];

        $codeTypologie = null === $attributs->etoiles ? null : (self::ETOILES[$attributs->etoiles] ?? null);
        // Garde référentiel : le code doit exister dans la liste effective.
        $codeTypologie = null === $codeTypologie ? null : LovValeurResolution::codePour(LieuLovCatalog::choicesFor('GENERALE_TYPOLOGIE'), $codeTypologie);
        if (null !== $codeTypologie && [] === $lieu->generaleTypologie()) {
            $propositions[] = new SuggestionProposee(
                action: SuggestionAction::RemplirChamp,
                champ: 'lieu_lov_typologie',
                label: 'Typologie',
                valeurActuelle: null,
                valeurProposee: LieuLovCatalog::choicesFor('GENERALE_TYPOLOGIE')[$codeTypologie] ?? $codeTypologie,
                score: null,
                payload: ['attribut' => 'GENERALE_TYPOLOGIE', 'codes' => [$codeTypologie]],
            );
        }
        // L'accept résout l'enseigne dans la LOV chaîne (et crée l'entrée si besoin).
        if (null !== $attributs->marque && [] === $lieu->generaleChainesGroupeHot()) {
            $propositions[] = new SuggestionProposee(
                action: SuggestionAction::RemplirChamp,
                champ: 'lieu_chaine',
                label: 'Chaîne / groupe hôtelier',
                valeurActuelle: null,
                valeurProposee: $attributs->marque,
                score: null,
            );
        }
        if (null !== $attributs->siteWeb
            && null === $lieu->generaleWebsiteUrl()
            && mb_strlen($attributs->siteWeb) <= Lieu::WEBSITE_MAX_LENGTH) {
            $propositions[] = new SuggestionProposee(
                action: SuggestionAction::RemplirChamp,
                champ: 'lieu_website',
                label: 'Site web',
                valeurActuelle: null,
                valeurProposee: $attributs->siteWeb,
                score: null,
            );
        }
        return $propositions;
    }
}
