<?php

declare(strict_types=1);

namespace App\Pim\Fusion;

use App\Pim\Enum\TypeFiche;
use App\Pim\Import\Schema\ColumnDefinition;
use App\Pim\Import\Schema\ColumnKind;
use App\Pim\Import\Schema\FicheImportSchemaRegistry;

/**
 * Champs pris en compte par la fusion de deux fiches d'une même gamme,
 * dérivés des schémas d'import (source de vérité du catalogue champ par
 * gamme) complétés des propriétés portées par la Fiche elle-même, absentes
 * des schémas. Deux familles :
 *  - champs comparables : une valeur unique, l'écran de comparaison propose
 *    un choix A/B (présélection par récence) ;
 *  - champs d'union : sélections multiples (LOV multi, sites de diffusion),
 *    fusionnées sans choix — rien ne se perd.
 */
final readonly class FusionChampsCatalogue
{
    /** targetPath sentinelle des champs portés par la Fiche (résolu via l'accesseur fiche() de l'agrégat). */
    public const CIBLE_FICHE = 'fiche';

    public function __construct(private FicheImportSchemaRegistry $schemas)
    {
    }

    /** @return list<ColumnDefinition> champs à choix A/B, dans l'ordre du schéma puis le supplément Fiche */
    public function champsComparables(TypeFiche $type): array
    {
        $champs = [];
        foreach ($this->schemas->for($type)->ficheColumns() as $column) {
            if ('code' === $column->header || in_array($column->kind, [ColumnKind::LovMulti, ColumnKind::SitesDiffusion], true)) {
                continue;
            }
            $champs[] = $column;
        }

        return array_merge($champs, self::supplementFiche());
    }

    /** @return list<ColumnDefinition> sélections multiples fusionnées en union */
    public function champsUnion(TypeFiche $type): array
    {
        return array_values(array_filter(
            $this->schemas->for($type)->ficheColumns(),
            static fn (ColumnDefinition $column): bool => in_array($column->kind, [ColumnKind::LovMulti, ColumnKind::SitesDiffusion], true),
        ));
    }

    /**
     * Propriétés de la Fiche absentes des schémas d'import : mêmes conventions
     * getter/setter (`businessPremium()` / `changeBusinessPremium()`), cible
     * résolue par l'accesseur `fiche()` de l'agrégat de gamme.
     *
     * @return list<ColumnDefinition>
     */
    public static function supplementFiche(): array
    {
        return [
            new ColumnDefinition('business_premium', ColumnKind::Bool, 'businessPremium', targetPath: self::CIBLE_FICHE, nullable: false),
            new ColumnDefinition('partenaire_bp', ColumnKind::Bool, 'partenaireBp', targetPath: self::CIBLE_FICHE, nullable: false),
        ];
    }

    /**
     * Path d'audit du champ (convention AuditPath::pour()),
     * pour dater sa dernière modification. Null pour les champs d'union,
     * qui ne demandent pas de présélection.
     */
    public function auditPath(TypeFiche $type, ColumnDefinition $column): ?string
    {
        if (in_array($column->kind, [ColumnKind::LovMulti, ColumnKind::SitesDiffusion], true)) {
            return null;
        }
        if ('label' === $column->target && null === $column->targetPath) {
            return 'nom';
        }

        return match ($column->targetPath) {
            'localisation', 'administratif', 'tarification' => $column->targetPath.'.'.$column->target,
            self::CIBLE_FICHE => 'fiche.'.$column->target,
            // Préfixe de gamme de AuditPath : le nom court de TypeFiche.
            null => $type->estOperationnel()
                ? $type->domaine().'.'.$column->target
                : throw new \DomainException('La fusion Traiteur n’est pas disponible.'),
            default => throw new \LogicException(sprintf('Chemin cible inattendu « %s » (colonne %s).', $column->targetPath, $column->header)),
        };
    }
}
