<?php

namespace App\Pim\Model\ProviderPortal\Mock\Sheet\Place;

class EnvironmentalImpactChoices
{
    /**
     * @return array<string, string>
     */
    public static function getChoices(): array
    {
        return [
            'Limiter la consomation d\'eau (si oui, préciser les mesures mise en œuvre)' => 'limiter-la-consomation-d-eau-si-oui-preciser-les-mesures-mise-en-oeuvre',
            'Robinets à détection automatique' => 'robinets-a-detection-automatique',
            'Mousseurs /réducteurs de débit sur les robinets des sanitaires' => 'mousseurs-reducteurs-de-debit-sur-les-robinets-des-sanitaires',
            'Chasses d\'eau double débit avec réservoir limité.' => 'chasses-d-eau-double-debit-avec-reservoir-limite',
            'Système de récupération d\'eau de pluie' => 'systeme-de-recuperation-d-eau-de-pluie',
            'Limiter la consomation d\'énergie  (si oui, préciser les mesures mise en œuvre)' => 'limiter-la-consomation-d-energie-si-oui-preciser-les-mesures-mise-en-oeuvre',
            'Adapter la température des chambres (recommandation à 19 degrés)' => 'adapter-la-temperature-des-chambres-recommandation-a-19-degres',
            'Limiter l’amplitude des boîtiers de commande de climatisation à +/-2°C.' => 'limiter-l-amplitude-des-boitiers-de-commande-de-climatisation-a-2-c',
            'Relier la gestion des appareils électriques et électroniques à la carte magnétique.' => 'relier-la-gestion-des-appareils-electriques-et-electroniques-a-la-carte-magnetique',
            'Privilégier les ampoules basse consommation' => 'privilegier-les-ampoules-basse-consommation',
            'Equiper les parties communes de détecteurs de présence.' => 'equiper-les-parties-communes-de-detecteurs-de-presence',
            'Détecteur d\'ouverture de fenetre couplé au chauffage' => 'detecteur-d-ouverture-de-fenetre-couple-au-chauffage',
            'Tri séléctif  et recyclage des déchets (si oui, préciser lesquelles)' => 'tri-selectif-et-recyclage-des-dechets-si-oui-preciser-lesquelles',
            'Déchets Verts' => 'dechets-verts',
            'Verre' => 'verre',
            'Papiers-Cartons, PMC (emballages plastiques, métalliques et cartons à boisson)' => 'papiers-cartons-pmc-emballages-plastiques-metalliques-et-cartons-a-boisson',
            'Electriques / électronique' => 'electriques-electronique',
            'Huiles, petits chimiques' => 'huiles-petits-chimiques',
            'Bouchons en liège' => 'bouchons-en-liege',
            'Mégots' => 'megots',
            'Collaboration avec des producteurs locaux' => 'collaboration-avec-des-producteurs-locaux',
            'Sensibilisation des clients à l\'impact environemental (charte en chambre, … )' => 'sensibilisation-des-clients-a-l-impact-environemental-charte-en-chambre',
            'Démarche zéro déchet' => 'demarche-zero-dechet',
            'Sensibilisation des collaborateurs' => 'sensibilisation-des-collaborateurs',
        ];
    }
}
