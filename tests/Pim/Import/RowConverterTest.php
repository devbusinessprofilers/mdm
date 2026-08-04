<?php

declare(strict_types=1);

namespace App\Tests\Pim\Import;

use App\Pim\Import\Dto\ConvertedValue;
use App\Pim\Import\Dto\RawCsvRow;
use App\Pim\Import\RowConverter;
use App\Pim\Import\Schema\LieuImportSchema;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class RowConverterTest extends KernelTestCase
{
    public function testConvertsScalarsLovListsAndCollectionGroups(): void
    {
        [$converter, $schema] = $this->services();

        $converted = $converter->convert($schema, new RawCsvRow(3, [
            'code' => '42',
            'label' => 'Château Test',
            'generale_typologie' => 'generale_typologie_1|GENERALE_TYPOLOGIE_2',
            'pmr_acces' => 'oui',
            'chambre_nb_total' => '12',
            'tarif_loc_salle_seul_journee' => '150.50',
            'desc_generale' => 'NULL',
            'salle_1_nom' => 'Salle Alpha',
            'salle_1_capacite_theatre' => '120',
            'salle_2_nom' => '',
        ]));

        self::assertSame([], $converted->errors);
        self::assertSame(42, $converted->code);

        $byHeader = [];
        foreach ($converted->fields as $field) {
            $byHeader[$field->column->header] = $field;
        }
        self::assertSame('Château Test', $byHeader['label']->value);
        self::assertSame(['GENERALE_TYPOLOGIE_1', 'GENERALE_TYPOLOGIE_2'], $byHeader['generale_typologie']->value);
        self::assertTrue($byHeader['pmr_acces']->value);
        self::assertSame(12, $byHeader['chambre_nb_total']->value);
        self::assertSame('150.50', $byHeader['tarif_loc_salle_seul_journee']->value);
        self::assertTrue($byHeader['desc_generale']->clear);

        self::assertArrayHasKey('salle', $converted->collections);
        self::assertCount(1, $converted->collections['salle']);
        $salle = [];
        foreach ($converted->collections['salle'][0] as $value) {
            self::assertInstanceOf(ConvertedValue::class, $value);
            $salle[$value->column->header] = $value->value;
        }
        self::assertSame('Salle Alpha', $salle['nom']);
        self::assertSame(120, $salle['capacite_theatre']);
    }

    public function testReportsErrorsWithColumnNames(): void
    {
        [$converter, $schema] = $this->services();

        $converted = $converter->convert($schema, new RawCsvRow(7, [
            'code' => 'abc',
            'label' => 'Fiche',
            'generale_typologie' => 'CODE_INCONNU',
            'chambre_nb_total' => 'douze',
            'pmr_acces' => 'peut-être',
            'periode_fermeture_1_nom' => 'Noël',
            'periode_fermeture_1_date_debut' => '25/12/2026',
        ]));

        $columns = array_map(static fn ($error): ?string => $error->column, $converted->errors);
        self::assertContains('code', $columns);
        self::assertContains('generale_typologie', $columns);
        self::assertContains('chambre_nb_total', $columns);
        self::assertContains('pmr_acces', $columns);
        self::assertContains('periode_fermeture_1_date_debut', $columns);
        foreach ($converted->errors as $error) {
            self::assertSame(7, $error->lineNumber);
        }
    }

    public function testNullSentinelIsRejectedOnNonNullableColumns(): void
    {
        [$converter, $schema] = $this->services();

        $converted = $converter->convert($schema, new RawCsvRow(4, ['pmr_acces' => 'NULL']));

        self::assertCount(1, $converted->errors);
        self::assertSame('pmr_acces', $converted->errors[0]->column);
    }

    /** @return array{RowConverter, LieuImportSchema} */
    private function services(): array
    {
        self::bootKernel();

        return [
            self::getContainer()->get(RowConverter::class),
            self::getContainer()->get(LieuImportSchema::class),
        ];
    }
}
