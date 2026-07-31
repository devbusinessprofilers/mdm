<?php

namespace App\Pim\Model\ProviderPortal\Mock\Sheet\Place;

class PurchaseCategoryChoices
{
    /**
     * @return array<string, string>
     */
    public static function getChoices(): array
    {
        return [
            'Conditionnement, logistique et transport' => 'conditionnement-logistique-et-transport',
            'Les espaces verts et paysagers' => 'les-espaces-verts-et-paysagers',
            'Nettoyage et entretien' => 'nettoyage-et-entretien',
            'Production industrielle (travail du bois, éléctricité, métallurgie, mécanique, textile, …)' => 'production-industrielle-travail-du-bois-electricite-metallurgie-mecanique-textile',
            'Restauration, hébergement et services touristiques' => 'restauration-hebergement-et-services-touristiques',
            'Prestations administratives (gestion back office, service client)' => 'prestations-administratives-gestion-back-office-service-client',
            'Services généraux' => 'services-generaux',
            'Production alimentaire' => 'production-alimentaire',
            'Communication et marketing' => 'communication-et-marketing',
            'Construction et bâtiment' => 'construction-et-batiment',
            'Impression, reprographie et marquage' => 'impression-reprographie-et-marquage',
            'Artisanat (artisanat d\'art, coffrets cadeaux, création de goodies, restauration de meubles, …)' => 'artisanat-artisanat-d-art-coffrets-cadeaux-creation-de-goodies-restauration-de-meubles',
            'Énergie, environnement, gestion des déchets' => 'energie-environnement-gestion-des-dechets',
            'Prestations intellectuelles (informatique et services numériques, …)' => 'prestations-intellectuelles-informatique-et-services-numeriques',
        ];
    }
}
