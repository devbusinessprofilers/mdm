<?php

declare(strict_types=1);

namespace App\Tests\Pim\Import;

use App\Pim\Enum\TypeFiche;
use App\Pim\Import\FicheImportTemplateGenerator;
use OpenSpout\Reader\XLSX\Reader;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class FicheImportTemplateGeneratorTest extends KernelTestCase
{
    private ?string $path = null;

    protected function tearDown(): void
    {
        if (null !== $this->path && is_file($this->path)) {
            unlink($this->path);
        }

        parent::tearDown();
    }

    public function testLieuTemplateHasADataSheetWithOnlyHeadersAndANoticeSheet(): void
    {
        self::bootKernel();
        $generator = self::getContainer()->get(FicheImportTemplateGenerator::class);

        $path = tempnam(sys_get_temp_dir(), 'mdm-template-test');
        self::assertIsString($path);
        $this->path = $path;
        $generator->write(TypeFiche::Lieu, $path);

        $sheets = $this->readSheets($path);
        self::assertSame([FicheImportTemplateGenerator::DATA_SHEET, FicheImportTemplateGenerator::NOTICE_SHEET], array_keys($sheets));

        $dataRows = $sheets[FicheImportTemplateGenerator::DATA_SHEET];
        self::assertCount(1, $dataRows, 'La feuille Données ne doit contenir que la ligne d’en-têtes.');
        self::assertSame($generator->headers(TypeFiche::Lieu), $dataRows[0]);
        self::assertContains('salle_1_nom', $dataRows[0]);
        self::assertContains('salle_20_climatisee', $dataRows[0]);
        self::assertContains('periode_fermeture_10_date_fin', $dataRows[0]);
        self::assertNotContains('salle_21_nom', $dataRows[0]);

        $notice = $sheets[FicheImportTemplateGenerator::NOTICE_SHEET];
        self::assertSame(['Colonne', 'Type attendu', 'Obligation', 'Aide'], $notice[0]);
        $flat = array_map(static fn (array $row): string => implode(';', $row), $notice);
        self::assertNotEmpty(preg_grep('/^label;texte;obligatoire;/', $flat));
        self::assertNotEmpty(preg_grep('/^salle_N_nom \(N = 1 à 20\);/', $flat));
        self::assertNotEmpty(preg_grep('/^GENERALE_TYPOLOGIE;GENERALE_TYPOLOGIE_1;/', $flat));

        self::assertSame('modele-import-lieu.xlsx', $generator->filename(TypeFiche::Lieu));
    }

    public function testHeadersAreUniqueForEveryType(): void
    {
        self::bootKernel();
        $generator = self::getContainer()->get(FicheImportTemplateGenerator::class);

        foreach ([TypeFiche::Lieu, TypeFiche::Activite, TypeFiche::Restaurant, TypeFiche::ServiceEvenementiel] as $type) {
            $headers = $generator->headers($type);
            self::assertSame(count($headers), count(array_unique($headers)), sprintf('En-têtes dupliqués pour %s.', $type->value));
            self::assertContains('code', $headers);
            self::assertContains('label', $headers);
        }
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
