<?php

declare(strict_types=1);

namespace App\Pim\Enum;

/**
 * Résultat d'une source du bouton « Enrichir ce qui manque » quand elle n'a
 * rien proposé : la valeur est journalisée telle quelle dans le run
 * (pim_fiche_enrichment_run.resultat) et lue par le journal /outils.
 */
enum ResultatSourceEnrichissement: string
{
    case Inactif = 'inactif';
    case NonConfiguree = 'non_configuree';
    case SansAdresse = 'sans_adresse';
    case Indisponible = 'indisponible';
    case VerificationEnfilee = 'verification_enfilee';

    public function libelle(): string
    {
        return match ($this) {
            self::Inactif => 'désactivée (/admin/parametres)',
            self::NonConfiguree => 'non configurée (clé API ou flux manquant)',
            self::SansAdresse => 'code postal manquant sur la fiche',
            self::Indisponible => 'API indisponible',
            self::VerificationEnfilee => 'vérification enfilée',
        };
    }
}
