<?php

declare(strict_types=1);

namespace App\Pim\Service;

/**
 * Catégories de photos (usage_code de RessourceLieu) et leurs libellés,
 * partagés entre le formulaire de métadonnées, le gestionnaire de photos
 * et les presenters qui affichent le badge de catégorie sur les vignettes.
 */
final class PhotoUsageCatalog
{
    /** Catégorie neutre appliquée à l'upload quand aucune n'est fournie. */
    public const DEFAUT = 'PHOTO_DIVERSE';

    /** @var array<string, string> code => libellé, dans l'ordre du formulaire */
    public const LABELS = [
        'PHOTO_FACADE' => 'Façade',
        'PHOTO_CHAMBRE' => 'Chambre',
        'PHOTO_RESTAURATION' => 'Restauration',
        'CONFIG_PHOTO_SALLE' => 'Salle de réunion',
        'PHOTO_DIVERSE' => 'Divers',
        'CONFIG_PLAN_SALLE' => 'Plan de salle',
        'LOISIR_EXTERNE_PHOTO' => 'Team building externe',
        'PHOTO' => 'Photos du lieu',
    ];

    public static function label(string $usage): string
    {
        return self::LABELS[$usage] ?? '';
    }

    private function __construct()
    {
    }
}
