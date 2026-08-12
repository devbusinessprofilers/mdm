<?php

declare(strict_types=1);

namespace App\Etl\Service;

use App\Enrichment\Repository\AttributeValueTranslationRepository;
use App\Pim\Lov\ActiviteLovCatalog;
use App\Pim\Lov\LieuLovCatalog;
use App\Pim\Lov\RestaurantLovCatalog;
use App\Pim\Lov\ServiceLovCatalog;
use App\Pim\Repository\ValeurAttributRepository;

/**
 * Construit le snapshot complet du dictionnaire LOV pour l'API marketplace
 * (PUT /api/pim/referentiels) : le dictionnaire effectif des quatre
 * catalogues, complété par les lignes en base (renommages, désactivations,
 * ajouts de l'admin) et les traductions de libellés disponibles.
 *
 * La marketplace enregistre tout en miroir et ne projette que les attributs
 * qu'elle mappe sur ses référentiels ; les codes restent le contrat stable,
 * les payloads de fiches ne portent jamais de libellés.
 */
final readonly class MarketplaceLovPayloadBuilder
{
    public function __construct(
        private ValeurAttributRepository $values,
        private AttributeValueTranslationRepository $translations,
    ) {
    }

    /** @return array<string, mixed> */
    public function build(): array
    {
        $dictionary = [];
        foreach ($this->effectiveChoices() as $attribute => $choices) {
            $position = 0;
            foreach ($choices as $code => $label) {
                $dictionary[$attribute][$code] = [
                    'label' => $label,
                    'position' => ++$position,
                    'active' => true,
                ];
            }
        }
        // La base est la source de vérité quand la ligne existe : renommage,
        // position, désactivation (une valeur désactivée sort des catalogues
        // mais doit être poussée pour être dépubliée côté marketplace).
        foreach ($this->values->findDictionaryRows() as $row) {
            $dictionary[(string) $row['attribute_code']][(string) $row['code']] = [
                'label' => (string) $row['label'],
                'position' => (int) $row['position'],
                'active' => (bool) $row['active'],
            ];
        }
        $translated = $this->translations->findAvailableDictionaryRows();
        $families = $this->families();
        ksort($dictionary);
        $attributes = [];
        foreach ($dictionary as $attribute => $values) {
            uasort(
                $values,
                static fn (array $a, array $b): int => [$a['position']] <=> [$b['position']],
            );
            $list = [];
            foreach ($values as $code => $value) {
                $list[] = $value + [
                    'code' => $code,
                    'translations' => $translated[$attribute][$code] ?? [],
                ];
            }
            $attributes[] = [
                'code' => $attribute,
                'famille' => $families[$attribute] ?? 'autre',
                'values' => $list,
            ];
        }

        return ['attributes' => $attributes];
    }

    /**
     * Famille de chaque attribut, d'après le catalogue qui le porte — sert au
     * rangement des listes dans l'admin marketplace. Les attributs partagés
     * (TYPE_CUISINE, DISPO_JOUR_OUVERTURE…) sont rattachés au lieu, les
     * attributs seulement en base restent « autre ».
     *
     * @return array<string, string>
     */
    private function families(): array
    {
        $families = [];
        foreach (array_keys(LieuLovCatalog::allChoices()) as $attribute) {
            $families[$attribute] ??= 'lieu';
        }
        foreach (array_keys(ActiviteLovCatalog::allChoices()) as $attribute) {
            $families[$attribute] ??= 'activite';
        }
        foreach (RestaurantLovCatalog::attributes() as $attribute) {
            $families[$attribute] ??= 'restaurant';
        }
        $families['TYPE_PRESTATAIRE'] ??= 'prestataire';

        return $families;
    }

    /**
     * Dictionnaire effectif tel que l'application le sert : la vue fusionnée
     * runtime + statique de chaque catalogue.
     *
     * @return array<string, array<string, string>>
     */
    private function effectiveChoices(): array
    {
        $all = [];
        foreach (array_keys(LieuLovCatalog::allChoices()) as $attribute) {
            $all[$attribute] = LieuLovCatalog::choicesFor($attribute);
        }
        foreach (array_keys(ActiviteLovCatalog::allChoices()) as $attribute) {
            $all[$attribute] = ($all[$attribute] ?? []) + ActiviteLovCatalog::choicesFor($attribute);
        }
        foreach (RestaurantLovCatalog::attributes() as $attribute) {
            $all[$attribute] = ($all[$attribute] ?? []) + RestaurantLovCatalog::values($attribute);
        }
        $all['TYPE_PRESTATAIRE'] = ($all['TYPE_PRESTATAIRE'] ?? []) + ServiceLovCatalog::prestations();

        return $all;
    }
}
