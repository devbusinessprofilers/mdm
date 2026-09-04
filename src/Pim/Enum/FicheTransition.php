<?php

declare(strict_types=1);

namespace App\Pim\Enum;

use App\Account\Security\FicheVoter;

/**
 * Les transitions de workflow déclenchées depuis l'éditeur de fiche, toutes
 * gammes : segment d'URL (`/referentiel/{gamme}/fiche/{id}/{segment}`), nom
 * technique (routes `app_pim_{domaine}_{nom}` et formulaires de
 * FicheActionFormFactory), droit exigé et libellé du bouton.
 */
enum FicheTransition: string
{
    case Soumettre = 'submit';
    case Valider = 'validate';
    case Publier = 'publish';
    case Refuser = 'reject';
    case Archiver = 'archive';
    case Desarchiver = 'unarchive';
    case Republier = 'republish';
    case Supprimer = 'delete';

    public function segment(): string
    {
        return match ($this) {
            self::Soumettre => 'soumettre',
            self::Valider => 'valider',
            self::Publier => 'publier',
            self::Refuser => 'refuser',
            self::Archiver => 'archiver',
            self::Desarchiver => 'desarchiver',
            self::Republier => 'republier',
            self::Supprimer => 'supprimer',
        };
    }

    public static function depuisSegment(string $segment): ?self
    {
        foreach (self::cases() as $transition) {
            if ($transition->segment() === $segment) {
                return $transition;
            }
        }

        return null;
    }

    /** Attribut du FicheVoter exigé sur la fiche. */
    public function droit(): string
    {
        return match ($this) {
            self::Soumettre => FicheVoter::SUBMIT,
            self::Valider, self::Refuser => FicheVoter::VALIDATE,
            self::Publier, self::Republier => FicheVoter::PUBLISH,
            self::Archiver, self::Desarchiver => FicheVoter::ARCHIVE,
            self::Supprimer => FicheVoter::DELETE,
        };
    }

    public function libelle(): string
    {
        return match ($this) {
            self::Soumettre => 'Soumettre à validation',
            self::Valider => 'Valider',
            self::Publier => 'Publier',
            self::Refuser => 'Refuser',
            self::Archiver => 'Archiver',
            self::Desarchiver => 'Désarchiver',
            self::Republier => 'Republier',
            self::Supprimer => 'Supprimer',
        };
    }

    /** Message de succès affiché après la transition. */
    public function succes(): string
    {
        return match ($this) {
            self::Soumettre => 'Fiche soumise à validation.',
            self::Valider => 'Fiche validée.',
            self::Publier => 'Fiche publiée.',
            self::Refuser => 'Fiche refusée et renvoyée en cours.',
            self::Archiver => 'Fiche archivée.',
            self::Desarchiver => 'Fiche désarchivée.',
            self::Republier => 'Fiche republiée.',
            self::Supprimer => 'Fiche supprimée.',
        };
    }
}
