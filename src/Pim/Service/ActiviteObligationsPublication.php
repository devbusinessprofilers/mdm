<?php

declare(strict_types=1);

namespace App\Pim\Service;

use Symfony\Component\Form\FormView;

/**
 * Champs bloquants à la soumission d'une Activité
 * (ValidActiviteValidator::submission), source de l'astérisque permanent de
 * l'éditeur. Le validateur porte une violation par couple (participants,
 * durées) sur le champ « minimum » : l'astérisque est aussi posé sur le
 * « maximum » (CHEMINS_JUMEAUX). La ville ne compte qu'en localisation fixe,
 * les régions qu'en localisation mobile sans « Toute la France ».
 */
final class ActiviteObligationsPublication
{
    public const CHEMINS = [
        'label',
        'prestataire',
        'descriptionGenerale',
        'thematiques',
        'types',
        'objectifs',
        'modeIntervention',
        'participantsMin',
        'dureeMinMinutes',
        ...self::CHEMINS_FIXE,
        ...self::CHEMINS_MOBILE,
    ];

    public const CHEMINS_FIXE = ['localisation.ville'];

    public const CHEMINS_MOBILE = ['regionsMobiles'];

    /** Champs marqués avec leur jumeau (une seule violation pour le couple). */
    public const CHEMINS_JUMEAUX = ['participantsMax', 'dureeMaxMinutes'];

    /** @return list<string> */
    public static function cheminsFormulaire(): array
    {
        return ObligationsPublicationMarqueur::cheminsFormulaire([...self::CHEMINS, ...self::CHEMINS_JUMEAUX], []);
    }

    public static function marquer(FormView $view): void
    {
        ObligationsPublicationMarqueur::marquer($view, self::cheminsFormulaire());
    }
}
