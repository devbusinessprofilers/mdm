<?php

declare(strict_types=1);

namespace App\Tests\Pim\Import\Legacy;

use App\Pim\Import\Legacy\LegacySqlDumpReader;
use PHPUnit\Framework\TestCase;

final class LegacySqlDumpReaderTest extends TestCase
{
    private ?string $file = null;

    protected function tearDown(): void
    {
        if (null !== $this->file) {
            @unlink($this->file);
        }
    }

    public function testRowsAreStreamedWithDecodedValues(): void
    {
        $dump = <<<'SQL'
            -- MySQL dump
            DROP TABLE IF EXISTS `bp_produit`;
            CREATE TABLE `bp_produit` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `nom_fr` varchar(200) NOT NULL,
              `description_fr` longtext DEFAULT NULL,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB;
            INSERT INTO `bp_produit` VALUES
            (1,'Café de Paris','Ligne 1\nLigne 2 avec l\'apostrophe et un \\ backslash'),
            (2,'Sans description',NULL);
            CREATE TABLE `autre_table` (
              `id` int(11) NOT NULL
            ) ENGINE=InnoDB;
            INSERT INTO `autre_table` VALUES
            (99);
            CREATE TABLE `i18n_translation_produit` (
              `id` int(11) NOT NULL,
              `produit_id` int(11) NOT NULL,
              `locale` varchar(8) NOT NULL,
              `field` varchar(32) NOT NULL,
              `content` longtext DEFAULT NULL,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB;
            INSERT INTO `i18n_translation_produit` VALUES
            (10,1,'en','descriptionFr','Line 1, with ''doubled quote'' style');
            SQL;
        $rows = iterator_to_array($this->read($dump, ['bp_produit', 'i18n_translation_produit']));

        self::assertCount(3, $rows);
        [$table, $first] = $rows[0];
        self::assertSame('bp_produit', $table);
        self::assertSame('1', $first['id']);
        self::assertSame('Café de Paris', $first['nom_fr']);
        self::assertSame("Ligne 1\nLigne 2 avec l'apostrophe et un \\ backslash", $first['description_fr']);
        self::assertNull($rows[1][1]['description_fr']);
        self::assertSame('i18n_translation_produit', $rows[2][0]);
        self::assertSame("Line 1, with 'doubled quote' style", $rows[2][1]['content']);
        self::assertSame('en', $rows[2][1]['locale']);
    }

    /** @param list<string> $tables */
    private function read(string $dump, array $tables): \Generator
    {
        $path = tempnam(sys_get_temp_dir(), 'mdm-dump-');
        self::assertIsString($path);
        $this->file = $path;
        file_put_contents($path, $dump);

        return (new LegacySqlDumpReader())->rows($path, $tables);
    }
}
