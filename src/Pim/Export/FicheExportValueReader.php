<?php

declare(strict_types=1);

namespace App\Pim\Export;

use App\Pim\Entity\AvecHorairesJours;
use App\Pim\Entity\Fiche;
use App\Pim\Entity\ValeurAttribut;
use App\Pim\Import\Schema\ColumnDefinition;
use App\Pim\Import\Schema\ColumnKind;
use App\Pim\Repository\SiteDiffusionRepository;

/**
 * Lit la valeur d'une colonne d'export sur une fiche : l'inverse du parseur
 * d'import (RowConverter), par les getters conventionnels `<target>()`. Les
 * formats simples sont ceux de l'import (oui/non, AAAA-MM-JJ) ; les colonnes
 * LOV portent les LIBELLÉS (séparés par « | » en multi) — c'est le format de
 * travail des utilisateurs de l'export, et l'import en masse (mode
 * écrasement) résout libellés comme codes.
 */
final class FicheExportValueReader
{
    /** Séparateur des valeurs multiples dans une cellule. */
    public const SEPARATEUR = ' | ';
    /** @var array<int, string>|null libellés du référentiel des sites de diffusion, par id */
    private ?array $sitesParId = null;

    public function __construct(private readonly SiteDiffusionRepository $sites)
    {
    }

    /**
     * @param object                               $porteur    agrégat de la gamme, ou entrée de collection
     * @param array<string, array<string, string>> $lovChoices
     *
     * @return list<int|float|string|null> une cellule par colonne
     */
    public function cellules(ColumnDefinition $column, object $porteur, Fiche $fiche, array $lovChoices): array
    {
        if ('code' === $column->header) {
            return [$fiche->code()];
        }
        if (ColumnKind::SitesDiffusion === $column->kind) {
            return [$this->libellesSites($fiche)];
        }
        if (ColumnKind::Horaire === $column->kind) {
            $heures = $porteur instanceof AvecHorairesJours
                ? ($porteur->horairesJours()[$column->horaireJour ?? ''] ?? null)
                : null;
            $ouverture = $heures['ouverture'] ?? null;
            $fermeture = $heures['fermeture'] ?? null;

            return [null === $ouverture && null === $fermeture ? null : sprintf('%s-%s', (string) $ouverture, (string) $fermeture)];
        }

        $cible = $this->cible($column, $porteur, $fiche);
        if (null === $cible) {
            return [null];
        }
        if (!method_exists($cible, $column->target)) {
            throw new \LogicException(sprintf('Getter %s absent sur %s (colonne %s).', $column->target, $cible::class, $column->header));
        }
        $valeur = $cible->{$column->target}();

        switch ($column->kind) {
            case ColumnKind::Text:
            case ColumnKind::Int:
            case ColumnKind::Decimal:
            case ColumnKind::Float:
                return [$valeur];
            case ColumnKind::Bool:
                return [null === $valeur ? null : ($valeur ? 'oui' : 'non')];
            case ColumnKind::Date:
                return [$valeur instanceof \DateTimeInterface ? $valeur->format('Y-m-d') : null];
            case ColumnKind::Time:
                return [$valeur instanceof \DateTimeInterface ? $valeur->format('H:i') : null];
            case ColumnKind::Enum:
                return [$valeur instanceof \BackedEnum ? (string) $valeur->value : null];
            case ColumnKind::StringList:
                return [is_array($valeur) && [] !== $valeur ? implode('|', $valeur) : null];
            case ColumnKind::LovMono:
                if (null === $valeur || '' === $valeur) {
                    return [null];
                }
                $choix = $lovChoices[$column->lovAttribute ?? ''] ?? [];

                // Code sans libellé connu : le code reste visible, rien ne se perd.
                return [$choix[$valeur] ?? $valeur];
            case ColumnKind::LovMulti:
                $codes = is_array($valeur) ? array_values($valeur) : [];
                if ([] === $codes) {
                    return [null];
                }
                $choix = $lovChoices[$column->lovAttribute ?? ''] ?? [];

                return [implode(self::SEPARATEUR, array_map(
                    static fn (string $code): string => $choix[$code] ?? $code,
                    $codes,
                ))];
            case ColumnKind::Prestataire:
                return [$valeur instanceof ValeurAttribut ? $valeur->label() : null];
            case ColumnKind::SitesDiffusion:
                throw new \LogicException('Cas traité en amont.');
        }
    }

    private function cible(ColumnDefinition $column, object $porteur, Fiche $fiche): ?object
    {
        if ('localisation' === $column->targetPath) {
            return $fiche->localisation();
        }
        if (null !== $column->targetPath) {
            return $porteur->{$column->targetPath}();
        }

        return $porteur;
    }

    private function libellesSites(Fiche $fiche): ?string
    {
        if (null === $this->sitesParId) {
            $this->sitesParId = [];
            foreach ($this->sites->findActifsOrdonnes() as $site) {
                $this->sitesParId[(int) $site->id()] = $site->label();
            }
        }
        $libelles = [];
        foreach ($fiche->siteDiffusionIds() as $id) {
            $libelles[] = $this->sitesParId[$id] ?? (string) $id;
        }

        return [] === $libelles ? null : implode('|', $libelles);
    }
}
