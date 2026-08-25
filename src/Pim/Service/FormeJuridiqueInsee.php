<?php

declare(strict_types=1);

namespace App\Pim\Service;

/**
 * Libellés des catégories juridiques INSEE (niveau III) courantes, pour
 * traduire le code `nature_juridique` de l'annuaire des entreprises en libellé
 * lisible du champ « Forme juridique ». Liste volontairement bornée aux
 * catégories fréquentes et sûres : un code inconnu ne propose RIEN (on ne
 * devine pas une forme juridique).
 */
final class FormeJuridiqueInsee
{
    /** @var array<int, string> code INSEE → libellé (clés numériques : PHP convertit les chaînes numériques) */
    private const LIBELLES = [
        '1000' => 'Entrepreneur individuel',
        '5202' => 'Société en nom collectif (SNC)',
        '5203' => 'Société en commandite simple',
        '5385' => 'Société d’exercice libéral à responsabilité limitée (SELARL)',
        '5498' => 'SARL unipersonnelle (EURL)',
        '5499' => 'Société à responsabilité limitée (SARL)',
        '5599' => 'Société anonyme à conseil d’administration (SA)',
        '5699' => 'Société anonyme à directoire (SA)',
        '5710' => 'Société par actions simplifiée (SAS)',
        '5720' => 'Société par actions simplifiée à associé unique (SASU)',
        '5785' => 'Société d’exercice libéral par actions simplifiée (SELAS)',
        '5800' => 'Société européenne',
        '6220' => 'Groupement d’intérêt économique (GIE)',
        '6521' => 'Société civile de placement collectif immobilier (SCPI)',
        '6533' => 'Groupement agricole d’exploitation en commun (GAEC)',
        '6540' => 'Société civile immobilière (SCI)',
        '6598' => 'Exploitation agricole à responsabilité limitée (EARL)',
        '6599' => 'Autre société civile',
        '7210' => 'Commune',
        '7220' => 'Département',
        '7230' => 'Région',
        '7346' => 'Communauté de communes',
        '9220' => 'Association déclarée',
        '9230' => 'Association déclarée reconnue d’utilité publique',
        '9300' => 'Fondation',
    ];

    public static function libelle(?string $code): ?string
    {
        $code = trim((string) $code);
        if (!ctype_digit($code)) {
            return null;
        }

        return self::LIBELLES[(int) $code] ?? null;
    }
}
