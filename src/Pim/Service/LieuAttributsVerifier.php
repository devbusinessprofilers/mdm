<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Enum\SuggestionAction;
use App\Pim\Lov\LieuLovCatalog;

/**
 * Confronte un lieu (hôtel, salle…) à Geoapify Place Details (tags
 * OpenStreetMap) pour proposer des attributs manquants, sans rien persister :
 * classement en étoiles (→ typologie), enseigne (→ chaîne / groupe hôtelier),
 * site web, équipements bien-être et installations, accès PMR et nombre de
 * chambres. Backfill seulement : on ne propose que des valeurs absentes de la
 * fiche (les LOV s'ajoutent en union). Tous pays dès qu'un GPS est présent.
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

    /**
     * Catégorie OSM (`tourism`/`amenity`) → typologie, pour les lieux sans
     * étoiles. Table volontairement courte : seules les correspondances sans
     * ambiguïté — hotel/hostel/motel absents (les codes hôtel sont indexés
     * par gamme d'étoiles, indécidable sans le tag `stars`).
     */
    private const CATEGORIE = [
        'guest_house' => 'GENERALE_TYPOLOGIE_25',
        'apartment' => 'GENERALE_TYPOLOGIE_13',
        'camp_site' => 'GENERALE_TYPOLOGIE_33',
        'caravan_site' => 'GENERALE_TYPOLOGIE_33',
        'museum' => 'GENERALE_TYPOLOGIE_26',
        'gallery' => 'GENERALE_TYPOLOGIE_26',
        'theme_park' => 'GENERALE_TYPOLOGIE_27',
        'casino' => 'GENERALE_TYPOLOGIE_18',
        'cinema' => 'GENERALE_TYPOLOGIE_36',
        'theatre' => 'GENERALE_TYPOLOGIE_31',
        'conference_centre' => 'GENERALE_TYPOLOGIE_20',
        'exhibition_centre' => 'GENERALE_TYPOLOGIE_20',
        'coworking_space' => 'GENERALE_TYPOLOGIE_23',
        'bar' => 'GENERALE_TYPOLOGIE_15',
        'pub' => 'GENERALE_TYPOLOGIE_15',
        'nightclub' => 'GENERALE_TYPOLOGIE_22',
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

        $codeEtoiles = null === $attributs->etoiles ? null : (self::ETOILES[$attributs->etoiles] ?? null);
        // À défaut d'étoiles, la catégorie OSM donne une typologie plus
        // grossière — score modeste pour le signaler à l'arbitre.
        $codeTypologie = $codeEtoiles ?? (null === $attributs->categorie ? null : (self::CATEGORIE[$attributs->categorie] ?? null));
        // Garde référentiel : le code doit exister dans la liste effective.
        $codeTypologie = null === $codeTypologie ? null : LovValeurResolution::codePour(LieuLovCatalog::choicesFor('GENERALE_TYPOLOGIE'), $codeTypologie);
        if (null !== $codeTypologie && [] === $lieu->generaleTypologie()) {
            $propositions[] = new SuggestionProposee(
                action: SuggestionAction::RemplirChamp,
                champ: 'lieu_lov_typologie',
                label: 'Typologie',
                valeurActuelle: null,
                valeurProposee: LieuLovCatalog::choicesFor('GENERALE_TYPOLOGIE')[$codeTypologie] ?? $codeTypologie,
                score: null === $codeEtoiles ? 0.5 : null,
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
        $this->ajouterLov($propositions, 'lieu_lov_bien_etre', 'BIEN_ETRE', 'Bien-être', self::bienEtre($attributs), $lieu->bienEtre());
        $this->ajouterLov($propositions, 'lieu_lov_installation', 'INSTALLATION', 'Installations', self::installations($attributs), $lieu->installation());
        // La case PMR n'est pas nullable : cochée = renseignée, décochée =
        // indistinguable de « non renseigné » — on ne propose que le cochage.
        if (true === $attributs->accesPmr && !$lieu->pmrAcces()) {
            $propositions[] = new SuggestionProposee(
                action: SuggestionAction::RemplirChamp,
                champ: 'lieu_pmr_acces',
                label: 'Accès PMR',
                valeurActuelle: null,
                valeurProposee: 'Oui',
                score: null,
                payload: ['bool' => true],
            );
        }
        if (null !== $attributs->chambres && null === $lieu->chambreNbTotal()) {
            $propositions[] = new SuggestionProposee(
                action: SuggestionAction::RemplirChamp,
                champ: 'lieu_chambre_nb_total',
                label: 'Nombre total de chambres',
                valeurActuelle: null,
                valeurProposee: (string) $attributs->chambres,
                score: null,
                payload: ['int' => $attributs->chambres],
            );
        }
        // Horaires OSM : seuls les motifs sans ambiguïté sont traduits — un
        // tag complexe ne produit simplement rien.
        $horairesOsm = null === $attributs->horairesOuverture ? null : HorairesOsm::parser($attributs->horairesOuverture);
        if (null !== $horairesOsm) {
            $this->ajouterLov($propositions, 'lieu_lov_jours_ouverture', 'DISPO_JOUR_OUVERTURE', 'Jours d\'ouverture', $horairesOsm['jours'], $lieu->joursOuverture());
            // Le getter replie sur l'amplitude globale historique : un repli
            // non nul compte comme renseigné, on ne propose qu'au vrai vide.
            if ([] !== $horairesOsm['horaires'] && null === $lieu->dispoHorairesJours()) {
                $propositions[] = new SuggestionProposee(
                    action: SuggestionAction::RemplirChamp,
                    champ: 'lieu_horaires_jours',
                    label: 'Horaires par jour',
                    valeurActuelle: null,
                    valeurProposee: HorairesOsm::resume($horairesOsm['horaires']),
                    score: null,
                    payload: ['horaires' => $horairesOsm['horaires']],
                );
            }
        }

        return $propositions;
    }

    /**
     * Même mécanique d'union que côté Restaurant : codes résolus contre le
     * référentiel effectif, seuls les ajouts (delta) sont proposés — le
     * payload porte l'attribut visé pour l'applier générique lieu_lov_*.
     *
     * @param list<SuggestionProposee> $propositions
     * @param list<string>             $codesProposes
     * @param list<string>             $codesActuels
     */
    private function ajouterLov(array &$propositions, string $champ, string $attribut, string $label, array $codesProposes, array $codesActuels): void
    {
        $choix = LieuLovCatalog::choicesFor($attribut);
        $resolus = [];
        foreach ($codesProposes as $candidat) {
            $code = LovValeurResolution::codePour($choix, $candidat);
            if (null !== $code) {
                $resolus[] = $code;
            }
        }
        $delta = array_values(array_diff(array_unique($resolus), $codesActuels));
        if ([] === $delta) {
            return;
        }
        $libelles = static fn (array $codes): string => implode(', ', array_map(static fn (string $code): string => $choix[$code] ?? $code, $codes));
        $propositions[] = new SuggestionProposee(
            action: SuggestionAction::RemplirChamp,
            champ: $champ,
            label: $label,
            valeurActuelle: '' === $libelles($codesActuels) ? null : $libelles($codesActuels),
            valeurProposee: $libelles($delta),
            score: null,
            payload: ['attribut' => $attribut, 'codes' => $delta],
        );
    }

    /** @return list<string> */
    private static function bienEtre(PlaceAttributs $attributs): array
    {
        $codes = [];
        if (true === $attributs->piscineInterieure) {
            $codes[] = 'BIEN_ETRE_2';
        }
        if (true === $attributs->piscineExterieure) {
            $codes[] = 'BIEN_ETRE_3';
        }
        if (true === $attributs->spa) {
            $codes[] = 'BIEN_ETRE_4';
        }
        if (true === $attributs->sauna) {
            $codes[] = 'BIEN_ETRE_5';
        }

        return $codes;
    }

    /** @return list<string> */
    private static function installations(PlaceAttributs $attributs): array
    {
        $codes = [];
        if (true === $attributs->jardin) {
            $codes[] = 'INSTALLATION_3';
        }
        if (true === $attributs->terrasse) {
            $codes[] = 'INSTALLATION_5';
        }
        if (true === $attributs->parking) {
            $codes[] = 'INSTALLATION_10';
        }
        if (true === $attributs->ascenseur) {
            $codes[] = 'INSTALLATION_12';
        }

        return $codes;
    }
}
