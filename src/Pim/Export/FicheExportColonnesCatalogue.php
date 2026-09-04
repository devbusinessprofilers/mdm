<?php

declare(strict_types=1);

namespace App\Pim\Export;

use App\Pim\Enum\TypeFiche;
use App\Pim\Import\Schema\CollectionSchema;
use App\Pim\Import\Schema\ColumnDefinition;
use App\Pim\Import\Schema\ColumnKind;
use App\Pim\Import\Schema\FicheImportSchemaRegistry;
use App\Pim\Service\FicheSectionsCatalogue;

/**
 * Rattache les colonnes des schémas d'import aux onglets de l'éditeur de
 * fiche (FicheSectionsCatalogue) : la modale d'export présente les colonnes
 * rangées comme le détail de la fiche, et l'export ne retient que les cochées.
 */
final readonly class FicheExportColonnesCatalogue
{
    public const AUTRES = 'Autres colonnes';

    /**
     * Colonnes du schéma sans propriété dans les sections de l'éditeur :
     * rattachement manuel (cible => titre d'onglet), verrouillé par le test
     * du catalogue.
     */
    private const RATTACHEMENTS = [
        'lieu' => [
            'generaleGammeLibelle' => 'Informations générales',
            // Colonnes horaires_* (une par jour) : la carte Disponibilités.
            'horaireJour' => 'Informations générales',
        ],
        'restaurant' => [
            'horaireJour' => 'Informations générales',
        ],
        'activite' => ['touteFrance' => 'Localisation & zone d\'intervention'],
    ];

    public function __construct(private FicheImportSchemaRegistry $schemas)
    {
    }

    /**
     * Onglets de la gamme dans l'ordre du détail de fiche, chacun avec ses
     * colonnes cochables. La colonne `code` n'y figure pas : identifiant de
     * la ligne, elle est toujours exportée.
     *
     * @return list<array{titre: string, colonnes: list<array{cle: string, libelle: string}>}>
     */
    public function groupesPour(TypeFiche $type): array
    {
        $sections = FicheSectionsCatalogue::pour($type);
        $autres = count($sections);
        /** @var array<int, list<array{cle: string, libelle: string}>> $parSection */
        $parSection = [];

        $schema = $this->schemas->for($type);
        foreach ($schema->ficheColumns() as $column) {
            if ('code' === $column->header) {
                continue;
            }
            $parSection[$this->indexSection($type, $column) ?? $autres][] = [
                'cle' => self::cleColonne($type, $column),
                'libelle' => $column->header,
            ];
        }
        foreach ($schema->collections() as $collection) {
            $parSection[$this->indexPropriete($type, $collection->getter) ?? $autres][] = [
                'cle' => self::cleCollection($type, $collection),
                'libelle' => sprintf('%s (groupes 1 à %d)', $collection->prefix, $collection->max),
            ];
        }

        $groupes = [];
        foreach ($sections as $index => $section) {
            if ([] !== ($parSection[$index] ?? [])) {
                $groupes[] = ['titre' => $section['titre'], 'colonnes' => $parSection[$index]];
            }
        }
        if ([] !== ($parSection[$autres] ?? [])) {
            $groupes[] = ['titre' => self::AUTRES, 'colonnes' => $parSection[$autres]];
        }

        return $groupes;
    }

    /**
     * Clés cochables des gammes demandées, dans l'ordre d'affichage de la
     * modale (gammes en ordre canonique, puis onglets, puis colonnes).
     *
     * @param list<TypeFiche> $types
     *
     * @return list<string>
     */
    public function clesPour(array $types): array
    {
        $cles = [];
        foreach (self::ordonnes($types) as $type) {
            foreach ($this->groupesPour($type) as $groupe) {
                foreach ($groupe['colonnes'] as $colonne) {
                    $cles[] = $colonne['cle'];
                }
            }
        }

        return $cles;
    }

    /**
     * @param list<TypeFiche> $types
     *
     * @return list<TypeFiche> ordre canonique des gammes, sans doublon
     */
    public static function ordonnes(array $types): array
    {
        return array_values(array_filter(
            FicheImportSchemaRegistry::supportedTypes(),
            static fn (TypeFiche $type): bool => in_array($type, $types, true),
        ));
    }

    /**
     * @param list<string> $cochees
     *
     * @return list<ColumnDefinition> colonnes retenues dans l'ordre du schéma, `code` toujours incluse
     */
    public function colonnesRetenues(TypeFiche $type, array $cochees): array
    {
        $retenues = [];
        foreach ($this->schemas->for($type)->ficheColumns() as $column) {
            if ('code' === $column->header || in_array(self::cleColonne($type, $column), $cochees, true)) {
                $retenues[] = $column;
            }
        }

        return $retenues;
    }

    /**
     * @param list<string> $cochees
     *
     * @return list<CollectionSchema>
     */
    public function collectionsRetenues(TypeFiche $type, array $cochees): array
    {
        return array_values(array_filter(
            $this->schemas->for($type)->collections(),
            static fn (CollectionSchema $collection): bool => in_array(self::cleCollection($type, $collection), $cochees, true),
        ));
    }

    private static function cleColonne(TypeFiche $type, ColumnDefinition $column): string
    {
        return $type->value.':'.$column->header;
    }

    private static function cleCollection(TypeFiche $type, CollectionSchema $collection): string
    {
        return $type->value.':collection:'.$collection->prefix;
    }

    private function indexSection(TypeFiche $type, ColumnDefinition $column): ?int
    {
        $titre = self::RATTACHEMENTS[$type->value][$column->target] ?? null;
        if (null !== $titre) {
            return $this->indexTitre($type, $titre);
        }
        // Les sites de diffusion sont un bloc, pas une propriété : l'onglet
        // « Booster ma visibilité » de chaque gamme.
        if (ColumnKind::SitesDiffusion === $column->kind) {
            return FicheSectionsCatalogue::indexBloc($type, 'sites');
        }
        $propriete = match ($column->targetPath) {
            'localisation', 'administratif', 'tarification' => $column->targetPath,
            default => $column->target,
        };

        return $this->indexPropriete($type, $propriete);
    }

    private function indexPropriete(TypeFiche $type, string $propriete): ?int
    {
        foreach (FicheSectionsCatalogue::pour($type) as $index => $section) {
            if (in_array($propriete, $section['proprietes'], true)) {
                return $index;
            }
        }

        return null;
    }

    private function indexTitre(TypeFiche $type, string $titre): ?int
    {
        foreach (FicheSectionsCatalogue::pour($type) as $index => $section) {
            if ($titre === $section['titre']) {
                return $index;
            }
        }

        return null;
    }
}
