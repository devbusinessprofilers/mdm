<?php

namespace App\Pim\Model\ProviderPortal\Mock\Sheet\Place;

class PurchaseChoices
{
    /**
     * @return array<string, string>
     */
    public static function getChoices(): array
    {
        return [
            'Cuisine avec des produits de saison' => 'cuisine-avec-des-produits-de-saison',
            'Cuisine avec des produits français ( ou privilégiant les circuits courts)' => 'cuisine-avec-des-produits-francais-ou-privilegiant-les-circuits-courts',
            'Cuisine avec des produits labelisés BIO' => 'cuisine-avec-des-produits-labelises-bio',
            'Achat de produits fabriqués en France (hors alimentation)' => 'achat-de-produits-fabriques-en-france-hors-alimentation',
            'Privilégie la réparation au le changement de votre matériel' => 'privilegie-la-reparation-au-le-changement-de-votre-materiel',
            'Fruits et légumes issus du potager in situ' => 'fruits-et-legumes-issus-du-potager-in-situ',
            'Non utilisation de capsules café ou de thé' => 'non-utilisation-de-capsules-cafe-ou-de-the',
            'Travail avec des traiteurs engagés et respectueux de l\'environnement' => 'travail-avec-des-traiteurs-engages-et-respectueux-de-l-environnement',
            'Utilisation de goodies éco concu, recyclé et recyclables' => 'utilisation-de-goodies-eco-concu-recycle-et-recyclables',
        ];
    }
}
