<?php

declare(strict_types=1);

namespace App\Tests\Etl;

use App\Etl\Service\SalesforceProduitsCsvExporter;
use App\Etl\Service\SalesforceSallesCsvExporter;
use PHPUnit\Framework\TestCase;

/**
 * Garde-fou sur les en-têtes CSV : ils doivent rester identiques à l'ancien
 * export attendu par Salesforce (nombre et bornes de colonnes).
 */
final class SalesforceCsvExporterHeadersTest extends TestCase
{
    public function testProduitsHeaderShape(): void
    {
        $entetes = SalesforceProduitsCsvExporter::ENTETES;

        self::assertCount(117, $entetes);
        self::assertSame('ID_PRODUCT', $entetes[0]);
        self::assertSame('S_PRODUCT_TRANSLATION_METRO', $entetes[array_key_last($entetes)]);
        self::assertContains('S_PRODUCT_TRANSLATION_TXT_PRINT_LES_PLUS_7', $entetes);
        self::assertContains('S_PRODUCT_TRANSLATION_EQUIP_TECHNIQUE_15', $entetes);
        self::assertSame($entetes, array_values(array_unique($entetes)), 'Les colonnes doivent être uniques et ordonnées.');
    }

    public function testSallesHeaderShape(): void
    {
        $entetes = SalesforceSallesCsvExporter::ENTETES;

        self::assertCount(22, $entetes);
        self::assertSame('ID_SALLE', $entetes[0]);
        self::assertSame('ID_PRODUCT', $entetes[1]);
        self::assertSame('S_SALLE_CONFERENCE', $entetes[array_key_last($entetes)]);
    }
}
