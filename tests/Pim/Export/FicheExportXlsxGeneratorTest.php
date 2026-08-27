<?php

declare(strict_types=1);

namespace App\Tests\Pim\Export;

use App\Pim\Entity\Lieu\Lieu;
use App\Pim\Entity\Restaurant\Restaurant;
use App\Pim\Export\FicheExportXlsxGenerator;
use App\Pim\Lov\LieuLovCatalog;
use App\Pim\Lov\RestaurantLovCatalog;
use OpenSpout\Reader\XLSX\Reader;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class FicheExportXlsxGeneratorTest extends KernelTestCase
{
    private ?string $path = null;

    protected function tearDown(): void
    {
        if (null !== $this->path && is_file($this->path)) {
            unlink($this->path);
        }

        parent::tearDown();
    }

    public function testUneFeuilleParGammeAvecLovEtListesDeroulantes(): void
    {
        self::bootKernel();
        $generator = self::getContainer()->get(FicheExportXlsxGenerator::class);

        $mice = (string) array_key_first(LieuLovCatalog::choicesFor('MICE_STATUT'));
        $lieu = new Lieu();
        $lieu->fiche()->assignImportedCode(101);
        $lieu->changeLabel('Château Test');
        $lieu->changeGeneraleTypologie(['GENERALE_TYPOLOGIE_1']);
        $lieu->changeMiceStatut($mice);
        $restaurant = new Restaurant();
        $restaurant->fiche()->assignImportedCode(202);
        $restaurant->changeLabel('Bistrot Test');

        $path = tempnam(sys_get_temp_dir(), 'mdm-export-test');
        self::assertIsString($path);
        $this->path = $path;

        $generator->write(
            ['restaurant' => [$restaurant], 'lieu' => [$lieu]],
            // type_cuisine des deux côtés : le même nom d'attribut LOV porte
            // des jeux de codes disjoints selon la gamme. mice_statut est la
            // mono-valeur (libellé + code synchronisé).
            ['lieu:label', 'lieu:generale_typologie', 'lieu:type_cuisine', 'lieu:mice_statut', 'restaurant:label', 'restaurant:types_cuisine'],
            $path,
        );

        $sheets = $this->readSheets($path);
        // L'ordre canonique des gammes prime sur l'ordre du tableau d'entrée.
        self::assertSame(['Lieux', 'Restaurants', FicheExportXlsxGenerator::LOV_SHEET], array_keys($sheets));

        // Colonnes retenues seulement ; les colonnes LOV portent les libellés,
        // le format de travail des utilisateurs de l'export.
        self::assertSame(['code', 'label', 'generale_typologie', 'type_cuisine', 'mice_statut'], $sheets['Lieux'][0]);
        $ligne = $sheets['Lieux'][1];
        self::assertSame('Château Test', $ligne[1]);
        self::assertSame(LieuLovCatalog::choicesFor('GENERALE_TYPOLOGIE')['GENERALE_TYPOLOGIE_1'], $ligne[2]);
        self::assertSame(LieuLovCatalog::choicesFor('MICE_STATUT')[$mice], $ligne[4]);

        self::assertSame(['code', 'label', 'types_cuisine'], $sheets['Restaurants'][0]);
        self::assertSame('Bistrot Test', $sheets['Restaurants'][1][1]);

        // La feuille LOV ne porte que les attributs des colonnes retenues ;
        // TYPE_CUISINE y figure en deux blocs, un par gamme (jeux disjoints).
        $lov = $sheets[FicheExportXlsxGenerator::LOV_SHEET];
        self::assertSame(['Attribut', 'Code', 'Libellé'], $lov[0]);
        $attributs = array_unique(array_column(array_slice($lov, 1), 0));
        self::assertSame(['GENERALE_TYPOLOGIE', 'TYPE_CUISINE', 'MICE_STATUT'], array_values($attributs));
        $codesCuisine = array_column(
            array_filter(array_slice($lov, 1), static fn (array $row): bool => 'TYPE_CUISINE' === $row[0]),
            1,
        );
        foreach (array_keys(LieuLovCatalog::choicesFor('TYPE_CUISINE')) as $code) {
            self::assertContains((string) $code, $codesCuisine);
        }
        foreach (array_keys(RestaurantLovCatalog::values('TYPE_CUISINE')) as $code) {
            self::assertContains((string) $code, $codesCuisine);
        }

        // La colonne libellé porte une liste déroulante alimentée par la
        // feuille LOV, et le code se calcule depuis le libellé choisi.
        $zip = new \ZipArchive();
        self::assertTrue($zip->open($path));
        $sheet1 = $zip->getFromName('xl/worksheets/sheet1.xml');
        $sheet2 = $zip->getFromName('xl/worksheets/sheet2.xml');
        self::assertIsString($sheet1);
        self::assertIsString($sheet2);
        self::assertStringContainsString('<dataValidation', $sheet1);
        self::assertStringContainsString('type="list"', $sheet1);
        // Toutes les dropdowns servent des libellés (colonne C de la feuille
        // LOV) : bloquantes en mono (mice_statut, colonne E), avertissement
        // non bloquant en multi (generale_typologie, colonne C).
        self::assertStringContainsString('LOV!$C$2', $sheet1);
        self::assertStringContainsString('sqref="C2:C2"', $sheet1);
        self::assertStringContainsString('errorStyle="warning"', $sheet1);
        self::assertStringContainsString('sqref="E2:E2"', $sheet1);
        self::assertStringNotContainsString('LOV!$B$', $sheet1);
        self::assertStringNotContainsString('<f>', $sheet1);
        // Chaque gamme vise le bloc TYPE_CUISINE de son propre jeu de valeurs ;
        // quand les deux jeux coïncident (LOV runtime chargées en base), le
        // bloc est partagé — c'est la déduplication voulue.
        $listes = static function (string $xml): array {
            preg_match_all('/<formula1>([^<]+)<\/formula1>/', $xml, $matches);

            return $matches[1];
        };
        self::assertNotEmpty($listes($sheet2));
        if (LieuLovCatalog::choicesFor('TYPE_CUISINE') != RestaurantLovCatalog::values('TYPE_CUISINE')) {
            self::assertSame([], array_intersect($listes($sheet1), $listes($sheet2)));
        } else {
            self::assertSame($listes($sheet2), array_values(array_intersect($listes($sheet1), $listes($sheet2))));
        }
        // Hauteur de ligne fixe : les cellules longues n'étirent pas les lignes.
        self::assertStringContainsString('customHeight="1"', $sheet1);
        $zip->close();
    }

    /** @return array<string, list<list<string>>> lignes non vides par nom de feuille */
    private function readSheets(string $path): array
    {
        $reader = new Reader();
        $reader->open($path);
        $sheets = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            $rows = [];
            foreach ($sheet->getRowIterator() as $row) {
                $cells = array_values(array_map(
                    static fn (mixed $cell): string => is_scalar($cell) ? trim((string) $cell) : '',
                    $row->toArray(),
                ));
                if ([] !== array_filter($cells, static fn (string $cell): bool => '' !== $cell)) {
                    $rows[] = $cells;
                }
            }
            $sheets[$sheet->getName()] = $rows;
        }
        $reader->close();

        return $sheets;
    }
}
