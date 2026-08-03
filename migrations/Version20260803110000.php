<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260803110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les traductions asynchrones des fiches et des listes de valeurs.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE pim_attribute_value ADD active TINYINT(1) DEFAULT 1 NOT NULL");
        $this->addSql("UPDATE pim_attribute_definition SET translatable = CASE WHEN code = 'PRESTATAIRE' THEN 0 ELSE 1 END");
        $this->addSql(<<<'SQL'
            CREATE TABLE enrichment_fiche_translation (
                id BINARY(16) NOT NULL,
                fiche_id BINARY(16) NOT NULL,
                field_path VARCHAR(191) NOT NULL,
                field_label VARCHAR(255) NOT NULL,
                locale VARCHAR(5) NOT NULL,
                source_text LONGTEXT NOT NULL,
                source_hash VARCHAR(64) NOT NULL,
                translated_text LONGTEXT DEFAULT NULL,
                translated_source_hash VARCHAR(64) DEFAULT NULL,
                suggested_text LONGTEXT DEFAULT NULL,
                origin VARCHAR(16) DEFAULT NULL,
                status VARCHAR(16) NOT NULL,
                request_token VARCHAR(26) DEFAULT NULL,
                last_error LONGTEXT DEFAULT NULL,
                manually_updated_by VARCHAR(255) DEFAULT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                INDEX IDX_88BE93FDDF522508 (fiche_id),
                INDEX IDX_ENRICHMENT_FICHE_TRANSLATION_STATUS (status, updated_at),
                UNIQUE INDEX UNIQ_ENRICHMENT_FICHE_FIELD_LOCALE (fiche_id, field_path, locale),
                PRIMARY KEY(id),
                CONSTRAINT FK_ENRICHMENT_FICHE_TRANSLATION FOREIGN KEY (fiche_id) REFERENCES pim_fiche (id) ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);
        $this->createLovTranslationTable('pim_attribute_definition_translation', 'attribute_id', 'pim_attribute_definition', 'UNIQ_PIM_ATTRIBUTE_DEFINITION_LOCALE', 'IDX_84A81382B6E62EFA');
        $this->createLovTranslationTable('pim_attribute_value_translation', 'value_id', 'pim_attribute_value', 'UNIQ_PIM_ATTRIBUTE_VALUE_LOCALE', 'IDX_6F34E18F920BBA2');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE pim_attribute_value_translation');
        $this->addSql('DROP TABLE pim_attribute_definition_translation');
        $this->addSql('DROP TABLE enrichment_fiche_translation');
        $this->addSql('ALTER TABLE pim_attribute_value DROP active');
    }

    private function createLovTranslationTable(string $table, string $foreignColumn, string $foreignTable, string $uniqueIndex, string $subjectIndex): void
    {
        $foreignKey = 'FK_'.strtoupper(substr(hash('sha256', $table), 0, 12));
        $this->addSql(sprintf(<<<'SQL'
            CREATE TABLE %s (
                id BINARY(16) NOT NULL,
                %s INT UNSIGNED NOT NULL,
                locale VARCHAR(5) NOT NULL,
                source_label VARCHAR(255) NOT NULL,
                source_hash VARCHAR(64) NOT NULL,
                translated_label VARCHAR(255) DEFAULT NULL,
                translated_source_hash VARCHAR(64) DEFAULT NULL,
                suggested_label VARCHAR(255) DEFAULT NULL,
                origin VARCHAR(16) DEFAULT NULL,
                status VARCHAR(16) NOT NULL,
                request_token VARCHAR(26) DEFAULT NULL,
                last_error LONGTEXT DEFAULT NULL,
                manually_updated_by VARCHAR(255) DEFAULT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                INDEX %s (%s),
                UNIQUE INDEX %s (%s, locale),
                PRIMARY KEY(id),
                CONSTRAINT %s FOREIGN KEY (%s) REFERENCES %s (id) ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL, $table, $foreignColumn, $subjectIndex, $foreignColumn, $uniqueIndex, $foreignColumn, $foreignKey, $foreignColumn, $foreignTable));
    }
}
