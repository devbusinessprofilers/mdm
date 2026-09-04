<?php

declare(strict_types=1);

namespace App\Pim\Service;

use Symfony\Component\Form\FormView;

/**
 * Champs bloquants à la soumission d'une fiche Restaurant
 * (ValidRestaurantValidator::submission), source unique de l'astérisque
 * permanent de l'éditeur (RestaurantType::finishView). Un test garantit
 * l'alignement avec les violations du validateur. Les photos (`ressources`)
 * et la salle exigée par une privatisation partielle sont couvertes ailleurs.
 */
final class RestaurantObligationsPublication
{
    public const CHEMINS = [
        'label',
        'siteOfficiel',
        'horairesJours',
        'descriptionGenerale',
        'capaciteAssiseMax',
        'capaciteEspacePrivatisable',
        'capaciteBanquet',
        'capaciteCocktail',
        'youtubeUrl',
        'typesRestaurant',
        'typesCuisine',
        'specificitesAlimentaires',
        'typesEvenement',
        'joursOuverture',
        'services',
        'equipements',
        'engagementsRse',
        'atouts',
        'localisation.pays',
        'localisation.region',
        'localisation.departement',
        'localisation.ruePostale',
        'localisation.codePostal',
        'localisation.ville',
        'localisation.latitude',
        'localisation.longitude',
        'acces.aeroport',
        'acces.gare',
    ];

    /** Obligations portées par la collection « acces » (au moins une ligne d'un type donné). */
    public const PSEUDO_CHEMINS = ['acces.aeroport', 'acces.gare'];

    /** @return list<string> */
    public static function cheminsFormulaire(): array
    {
        return ObligationsPublicationMarqueur::cheminsFormulaire(self::CHEMINS, self::PSEUDO_CHEMINS);
    }

    public static function marquer(FormView $view): void
    {
        ObligationsPublicationMarqueur::marquer($view, self::cheminsFormulaire());
    }
}
