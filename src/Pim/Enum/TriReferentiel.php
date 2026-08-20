<?php

declare(strict_types=1);

namespace App\Pim\Enum;

/**
 * Ordre de la liste du référentiel. La valeur circule dans les URL (f[tri])
 * et dans les vues enregistrées ; le SQL correspondant vit dans
 * ReferentielRepository, seuls la colonne logique et le sens sont portés ici.
 */
enum TriReferentiel: string
{
    case ModifDesc = 'modif_desc';
    case ModifAsc = 'modif_asc';
    case NomAsc = 'nom_asc';
    case NomDesc = 'nom_desc';
    case GammeAsc = 'gamme_asc';
    case GammeDesc = 'gamme_desc';
    case PaysAsc = 'pays_asc';
    case PaysDesc = 'pays_desc';
    case StatutAsc = 'statut_asc';
    case StatutDesc = 'statut_desc';
    case CompletudeAsc = 'completude_asc';
    case CompletudeDesc = 'completude_desc';
    case DiffusionAsc = 'diffusion_asc';
    case DiffusionDesc = 'diffusion_desc';

    public const DEFAUT = self::ModifDesc;

    public function colonne(): string
    {
        [$colonne] = explode('_', $this->value);

        return $colonne;
    }

    /** @return 'ASC'|'DESC' */
    public function direction(): string
    {
        return str_ends_with($this->value, '_asc') ? 'ASC' : 'DESC';
    }

    public function estDefaut(): bool
    {
        return self::DEFAUT === $this;
    }

    public function libelle(): string
    {
        return match ($this) {
            self::ModifDesc => 'dernière modification',
            self::ModifAsc => 'modification la plus ancienne',
            self::NomAsc => 'nom (A → Z)',
            self::NomDesc => 'nom (Z → A)',
            self::GammeAsc => 'gamme (A → Z)',
            self::GammeDesc => 'gamme (Z → A)',
            self::PaysAsc => 'pays (A → Z)',
            self::PaysDesc => 'pays (Z → A)',
            self::StatutAsc => 'statut (A → Z)',
            self::StatutDesc => 'statut (Z → A)',
            self::CompletudeAsc => 'complétude croissante',
            self::CompletudeDesc => 'complétude décroissante',
            self::DiffusionAsc => 'diffusion croissante',
            self::DiffusionDesc => 'diffusion décroissante',
        };
    }

    /**
     * Cible d'un clic sur l'en-tête de la colonne donnée : inverse le sens si
     * elle porte déjà le tri, sinon son sens naturel (alphabétique ascendant,
     * dates et quantités descendantes).
     */
    public static function pourColonne(string $colonne, self $courant): self
    {
        if ($courant->colonne() === $colonne) {
            return self::from($colonne.('ASC' === $courant->direction() ? '_desc' : '_asc'));
        }

        return match ($colonne) {
            'modif', 'completude', 'diffusion' => self::from($colonne.'_desc'),
            default => self::from($colonne.'_asc'),
        };
    }
}
