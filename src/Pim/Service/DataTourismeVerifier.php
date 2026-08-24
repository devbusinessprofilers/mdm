<?php

declare(strict_types=1);

namespace App\Pim\Service;

use App\Pim\Entity\Activite\Activite;
use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Enum\SuggestionAction;
use App\Pim\Lov\LieuLovCatalog;
use App\Pim\Service\DataTourisme\DataTourismeIndex;
use App\Pim\Service\DataTourisme\DataTourismePoi;

/**
 * Rapproche une fiche (Lieu ou Activité) de son équivalent DATAtourisme et en
 * tire des suggestions à arbitrer, sans rien persister : description générale
 * (backfill si vide) et, pour les lieux, équipements bien-être / installations
 * déduits des libellés du flux. Backfill seulement — jamais d'écrasement.
 */
final readonly class DataTourismeVerifier
{
    private const APERCU = 200;
    private const DESC_MAX_LIEU = Lieu::DESCRIPTION_MAX_LENGTH;
    private const DESC_MAX_ACTIVITE = 2000;

    /** Mot-clé contenu dans un libellé DATAtourisme → [attribut LOV, code]. Ordre : du plus spécifique au plus générique. */
    private const FEATURE_MAP = [
        'piscine intérieure' => ['BIEN_ETRE', 'BIEN_ETRE_2'],
        'piscine couverte' => ['BIEN_ETRE', 'BIEN_ETRE_2'],
        'piscine' => ['BIEN_ETRE', 'BIEN_ETRE_3'],
        'thalasso' => ['BIEN_ETRE', 'BIEN_ETRE_1'],
        'spa' => ['BIEN_ETRE', 'BIEN_ETRE_4'],
        'sauna' => ['BIEN_ETRE', 'BIEN_ETRE_5'],
        'hammam' => ['BIEN_ETRE', 'BIEN_ETRE_6'],
        'jacuzzi' => ['BIEN_ETRE', 'BIEN_ETRE_7'],
        'bain à remous' => ['BIEN_ETRE', 'BIEN_ETRE_7'],
        'salle de sport' => ['BIEN_ETRE', 'BIEN_ETRE_9'],
        'fitness' => ['BIEN_ETRE', 'BIEN_ETRE_9'],
        'massage' => ['BIEN_ETRE', 'BIEN_ETRE_10'],
        'plage privée' => ['INSTALLATION', 'INSTALLATION_8'],
        'espace de coworking' => ['INSTALLATION', 'INSTALLATION_9'],
        'coworking' => ['INSTALLATION', 'INSTALLATION_9'],
        'parking' => ['INSTALLATION', 'INSTALLATION_10'],
        'ascenseur' => ['INSTALLATION', 'INSTALLATION_12'],
        'rooftop' => ['INSTALLATION', 'INSTALLATION_4'],
        'terrasse' => ['INSTALLATION', 'INSTALLATION_5'],
        'jardin' => ['INSTALLATION', 'INSTALLATION_3'],
        'parc' => ['INSTALLATION', 'INSTALLATION_3'],
    ];

    /** @return list<SuggestionProposee> */
    public function analyserLieu(Lieu $lieu, DataTourismeIndex $index): array
    {
        $poi = $this->rapprocher($lieu->label(), $lieu->localisation()?->codePostal(), $index);
        if (null === $poi) {
            return [];
        }
        $propositions = [];
        $description = self::description($poi, 'lieu_desc_generale', $lieu->descGenerale(), self::DESC_MAX_LIEU);
        if (null !== $description) {
            $propositions[] = $description;
        }
        foreach (self::equipements($poi->features) as $attribut => $codes) {
            $actuels = 'BIEN_ETRE' === $attribut ? $lieu->bienEtre() : $lieu->installation();
            $ligne = self::lov('lieu_lov_'.$attribut, $attribut, $codes, $actuels);
            if (null !== $ligne) {
                $propositions[] = $ligne;
            }
        }

        return $propositions;
    }

    /** @return list<SuggestionProposee> */
    public function analyserActivite(Activite $activite, DataTourismeIndex $index): array
    {
        $poi = $this->rapprocher($activite->label(), $activite->localisation()?->codePostal(), $index);
        if (null === $poi) {
            return [];
        }
        $description = self::description($poi, 'activite_desc_generale', $activite->descriptionGenerale(), self::DESC_MAX_ACTIVITE);

        return null === $description ? [] : [$description];
    }

    private function rapprocher(?string $label, ?string $codePostal, DataTourismeIndex $index): ?DataTourismePoi
    {
        if (null === $label || '' === trim($label)) {
            return null;
        }

        return $index->rapprocher($label, $codePostal);
    }

    private static function description(DataTourismePoi $poi, string $champ, ?string $actuel, int $max): ?SuggestionProposee
    {
        if (null !== $actuel || null === $poi->description) {
            return null;
        }
        $texte = mb_substr(trim($poi->description), 0, $max);
        if ('' === $texte) {
            return null;
        }
        $apercu = mb_substr($texte, 0, self::APERCU);

        return new SuggestionProposee(
            action: SuggestionAction::RemplirChamp,
            champ: $champ,
            label: 'Description générale',
            valeurActuelle: null,
            valeurProposee: mb_strlen($texte) > self::APERCU ? $apercu.'…' : $apercu,
            score: null,
            payload: ['text' => $texte],
        );
    }

    /**
     * @param list<string> $codesProposes
     * @param list<string> $codesActuels
     */
    private static function lov(string $champ, string $attribut, array $codesProposes, array $codesActuels): ?SuggestionProposee
    {
        $delta = array_values(array_diff(array_unique($codesProposes), $codesActuels));
        if ([] === $delta) {
            return null;
        }

        return new SuggestionProposee(
            action: SuggestionAction::RemplirChamp,
            champ: $champ,
            label: 'BIEN_ETRE' === $attribut ? 'Bien-être' : 'Installations',
            valeurActuelle: null,
            valeurProposee: self::libelles($attribut, $delta),
            score: null,
            payload: ['codes' => $delta, 'attribut' => $attribut],
        );
    }

    /**
     * @param list<string> $features libellés DATAtourisme (minuscules)
     *
     * @return array<string, list<string>> codes LOV groupés par attribut
     */
    private static function equipements(array $features): array
    {
        $parAttribut = [];
        foreach ($features as $feature) {
            foreach (self::FEATURE_MAP as $motCle => [$attribut, $code]) {
                if (str_contains($feature, $motCle)) {
                    $parAttribut[$attribut][] = $code;
                    break; // le mot-clé le plus spécifique l'emporte
                }
            }
        }

        return array_map(static fn (array $codes): array => array_values(array_unique($codes)), $parAttribut);
    }

    /** @param list<string> $codes */
    private static function libelles(string $attribut, array $codes): string
    {
        $valeurs = LieuLovCatalog::choicesFor($attribut);

        return implode(', ', array_map(static fn (string $code): string => $valeurs[$code] ?? $code, $codes));
    }
}
