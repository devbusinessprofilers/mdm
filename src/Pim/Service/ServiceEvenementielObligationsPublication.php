<?php

declare(strict_types=1);

namespace App\Pim\Service;

use Symfony\Component\Form\FormView;

/**
 * Champs bloquants à la soumission d'un Service événementiel
 * (ValidServiceEvenementielValidator::submission), source de l'astérisque
 * permanent de l'éditeur. L'adresse ne compte qu'en localisation fixe, les
 * zones mobiles qu'en localisation mobile : l'astérisque reste affiché sur
 * les deux blocs, seul le bloc du rayon d'action choisi est visible.
 */
final class ServiceEvenementielObligationsPublication
{
    public const CHEMINS = [
        'label',
        'descriptionGenerale',
        'modeIntervention',
        'youtubeUrl',
        'prestations',
        'tarifParPrestation',
        'tarifParPersonne',
        'tarifParJour',
        'tarifParDemiJournee',
        'tarifParHeure',
        ...self::CHEMINS_FIXE,
        ...self::CHEMINS_MOBILE,
    ];

    /** Exigés seulement en localisation fixe. */
    public const CHEMINS_FIXE = [
        'localisation.pays',
        'localisation.region',
        'localisation.departement',
        'localisation.ruePostale',
        'localisation.codePostal',
        'localisation.ville',
        'localisation.arrondissement',
        'localisation.latitude',
        'localisation.longitude',
    ];

    /** Exigés seulement en localisation mobile. */
    public const CHEMINS_MOBILE = ['paysMobiles', 'regionsMobiles', 'departementsMobiles'];

    /** @return list<string> */
    public static function cheminsFormulaire(): array
    {
        return ObligationsPublicationMarqueur::cheminsFormulaire(self::CHEMINS, []);
    }

    public static function marquer(FormView $view): void
    {
        ObligationsPublicationMarqueur::marquer($view, self::cheminsFormulaire());
    }
}
