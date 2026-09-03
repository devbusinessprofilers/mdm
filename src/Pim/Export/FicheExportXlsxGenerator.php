<?php

declare(strict_types=1);

namespace App\Pim\Export;

use App\Pim\Enum\TypeFiche;
use App\Pim\Import\Schema\ColumnKind;
use App\Pim\Import\Schema\FicheImportSchemaRegistry;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Validation\CellReference;
use OpenSpout\Writer\XLSX\Validation\ErrorStyle;
use OpenSpout\Writer\XLSX\Validation\Rules\ListValidationRule;
use OpenSpout\Writer\XLSX\Validation\ValidationDisplay;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Écrit le classeur d'export du référentiel : une feuille de données par
 * gamme présente (colonnes retenues dans la modale), puis une feuille « LOV »
 * réservoir des listes de valeurs. Les colonnes LOV portent les LIBELLÉS —
 * le format de travail des utilisateurs — avec une liste déroulante par
 * colonne (bloquante en mono-valeur, avertissement en multi) ; l'import en
 * masse (mode écrasement) résout libellés comme codes au retour.
 */
final readonly class FicheExportXlsxGenerator
{
    public const LOV_SHEET = 'LOV';

    // Hauteur de ligne et largeur de colonne fixes : les descriptions
    // multi-lignes n'étirent pas les lignes, le tableau reste balayable.
    private const HAUTEUR_LIGNE = 15.0;
    private const LARGEUR_COLONNE = 24.0;

    public function __construct(
        private FicheImportSchemaRegistry $schemas,
        private FicheExportColonnesCatalogue $catalogue,
        private FicheExportValueReader $reader,
    ) {
    }

    public static function nomFeuille(TypeFiche $type): string
    {
        return match ($type) {
            TypeFiche::Lieu => 'Lieux',
            TypeFiche::Restaurant => 'Restaurants',
            TypeFiche::Activite => 'Activités',
            TypeFiche::ServiceEvenementiel => 'Services',
            TypeFiche::Traiteur => 'Traiteurs',
        };
    }

    /**
     * @param array<string, iterable<object>> $aggregatsParType agrégats par TypeFiche->value, ordre libre
     * @param list<string>                    $clesCochees      clés du FicheExportColonnesCatalogue
     */
    public function write(array $aggregatsParType, array $clesCochees, string $path): void
    {
        $types = array_values(array_filter(
            FicheImportSchemaRegistry::supportedTypes(),
            static fn (TypeFiche $type): bool => array_key_exists($type->value, $aggregatsParType),
        ));

        // Plan de la feuille LOV : un bloc de lignes contiguës par jeu de
        // valeurs, borné avant l'écriture pour que les validations le visent.
        // Deux gammes peuvent porter le même nom d'attribut avec des jeux
        // disjoints (TYPE_CUISINE lieu ≠ restaurant) : les plages se
        // résolvent par gamme, les blocs identiques sont partagés.
        /** @var list<array{attribut: string, choices: array<string, string>}> $blocs */
        $blocs = [];
        $blocParGamme = [];
        foreach ($types as $type) {
            $choices = $this->schemas->for($type)->lovChoices();
            foreach ($this->catalogue->colonnesRetenues($type, $clesCochees) as $column) {
                $attribut = $column->lovAttribute;
                if (null === $attribut || [] === ($choices[$attribut] ?? [])) {
                    continue;
                }
                $cle = $type->value.':'.$attribut;
                if (isset($blocParGamme[$cle])) {
                    continue;
                }
                $index = null;
                foreach ($blocs as $i => $bloc) {
                    if ($bloc['attribut'] === $attribut && $bloc['choices'] === $choices[$attribut]) {
                        $index = $i;
                        break;
                    }
                }
                if (null === $index) {
                    $blocs[] = ['attribut' => $attribut, 'choices' => $choices[$attribut]];
                    $index = array_key_last($blocs);
                }
                $blocParGamme[$cle] = $index;
            }
        }
        $ligne = 2; // la ligne 1 porte l'en-tête de la feuille LOV
        $bornes = [];
        foreach ($blocs as $index => $bloc) {
            $bornes[$index] = [max(1, $ligne), max(1, $ligne + count($bloc['choices']) - 1)];
            $ligne += count($bloc['choices']);
        }
        $plages = [];
        foreach ($blocParGamme as $cle => $index) {
            $plages[$cle] = $bornes[$index];
        }

        $options = new Options(
            DEFAULT_COLUMN_WIDTH: self::LARGEUR_COLONNE,
            DEFAULT_ROW_HEIGHT: self::HAUTEUR_LIGNE,
        );
        $writer = new Writer($options);
        $writer->openToFile($path);

        try {
            foreach ($types as $index => $type) {
                $sheet = 0 === $index ? $writer->getCurrentSheet() : $writer->addNewSheetAndMakeItCurrent();
                $sheet->setName(self::nomFeuille($type));
                $this->ecrireFeuille($writer, $options, $index, $type, $aggregatsParType[$type->value], $clesCochees, $plages);
            }

            $lov = [] === $types ? $writer->getCurrentSheet() : $writer->addNewSheetAndMakeItCurrent();
            $lov->setName(self::LOV_SHEET);
            $writer->addRow(Row::fromValues(['Attribut', 'Code', 'Libellé']));
            foreach ($blocs as $bloc) {
                foreach ($bloc['choices'] as $code => $libelle) {
                    $writer->addRow(Row::fromValues([$bloc['attribut'], (string) $code, $libelle]));
                }
            }
        } finally {
            $writer->close();
        }
    }

    /**
     * @param int<0, max>                                    $sheetIndex
     * @param iterable<object>                               $aggregats
     * @param list<string>                                   $clesCochees
     * @param array<string, array{int<1, max>, int<1, max>}> $plages      lignes [début, fin] des attributs sur la feuille LOV
     */
    private function ecrireFeuille(
        Writer $writer,
        Options $options,
        int $sheetIndex,
        TypeFiche $type,
        iterable $aggregats,
        array $clesCochees,
        array $plages,
    ): void {
        $schema = $this->schemas->for($type);
        $colonnes = $this->catalogue->colonnesRetenues($type, $clesCochees);
        $collections = $this->catalogue->collectionsRetenues($type, $clesCochees);
        $lovChoices = $schema->lovChoices();

        $entetes = [];
        $validations = [];
        foreach ($colonnes as $column) {
            if (null !== $column->lovAttribute && isset($plages[$type->value.':'.$column->lovAttribute])) {
                $validations[] = [count($entetes), $column->lovAttribute, $column->kind];
            }
            $entetes[] = $column->header;
        }
        foreach ($collections as $collection) {
            for ($groupe = 1; $groupe <= $collection->max; ++$groupe) {
                foreach ($collection->columns as $column) {
                    $entetes[] = $collection->header($groupe, $column);
                }
            }
        }
        $writer->addRow(Row::fromValues($entetes, self::HAUTEUR_LIGNE));

        $nb = 0;
        foreach ($aggregats as $aggregate) {
            $fiche = $schema->ficheOf($aggregate);
            $valeurs = [];
            foreach ($colonnes as $column) {
                foreach ($this->reader->cellules($column, $aggregate, $fiche, $lovChoices) as $cellule) {
                    $valeurs[] = $cellule;
                }
            }
            foreach ($collections as $collection) {
                $entrees = array_values($aggregate->{$collection->getter}()->toArray());
                for ($position = 0; $position < $collection->max; ++$position) {
                    $entree = $entrees[$position] ?? null;
                    foreach ($collection->columns as $column) {
                        if (null === $entree) {
                            $valeurs[] = null;
                            continue;
                        }
                        foreach ($this->reader->cellules($column, $entree, $fiche, $lovChoices) as $cellule) {
                            $valeurs[] = $cellule;
                        }
                    }
                }
            }
            $writer->addRow(Row::fromValues($valeurs, self::HAUTEUR_LIGNE));
            ++$nb;
        }

        // Listes déroulantes de LIBELLÉS (colonne C de la feuille LOV) :
        // bloquantes en mono-valeur ; en multi la dropdown reste une aide non
        // bloquante, une dropdown Excel ne saisissant qu'une valeur à la fois.
        foreach ($validations as [$indexColonne, $attribut, $kind]) {
            [$debut, $fin] = $plages[$type->value.':'.$attribut];
            $options->addValidation(
                $indexColonne,
                2,
                $indexColonne,
                max(2, $nb + 1),
                new ListValidationRule(new CellReference(2, $debut, 2, $fin, true, self::LOV_SHEET)),
                ColumnKind::LovMono === $kind
                    ? new ValidationDisplay(promptTitle: $attribut, prompt: 'Choisir un libellé de la liste.')
                    : new ValidationDisplay(
                        errorStyle: ErrorStyle::Warning,
                        promptTitle: $attribut,
                        prompt: 'Libellés multiples séparés par « | » — la liste propose une valeur à la fois.',
                    ),
                $sheetIndex,
            );
        }
    }
}
