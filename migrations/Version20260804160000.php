<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260804160000 extends AbstractMigration
{
    public function getDescription(): string { return 'Ajoute les extractions documentaires Box OCR et leurs suggestions révisables.'; }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE ocr_document_extraction (
                id BINARY(16) NOT NULL, fiche_id BINARY(16) NOT NULL, media_asset_id BINARY(16) NOT NULL, retry_of_id BINARY(16) DEFAULT NULL,
                document_checksum VARCHAR(64) NOT NULL, document_category VARCHAR(64) NOT NULL, schema_snapshot JSON NOT NULL, schema_fingerprint VARCHAR(64) NOT NULL,
                status VARCHAR(32) NOT NULL, created_by VARCHAR(180) NOT NULL, attempts SMALLINT UNSIGNED DEFAULT 0 NOT NULL, page_count SMALLINT UNSIGNED DEFAULT NULL,
                started_at DATETIME DEFAULT NULL, finished_at DATETIME DEFAULT NULL, error_message LONGTEXT DEFAULT NULL,
                raw_response JSON DEFAULT NULL, provider_agent VARCHAR(100) DEFAULT NULL, provider_model VARCHAR(100) DEFAULT NULL, temporary_box_file_ids JSON NOT NULL,
                created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
                INDEX IDX_OCR_EXTRACTION_RETRY (retry_of_id), INDEX IDX_OCR_EXTRACTION_FICHE_CREATED (fiche_id, created_at), INDEX IDX_OCR_EXTRACTION_STATUS (status, updated_at), INDEX IDX_OCR_EXTRACTION_MEDIA (media_asset_id),
                CONSTRAINT FK_OCR_EXTRACTION_FICHE FOREIGN KEY (fiche_id) REFERENCES pim_fiche (id) ON DELETE CASCADE,
                CONSTRAINT FK_OCR_EXTRACTION_MEDIA FOREIGN KEY (media_asset_id) REFERENCES dam_media_asset (id) ON DELETE RESTRICT,
                CONSTRAINT FK_OCR_EXTRACTION_RETRY FOREIGN KEY (retry_of_id) REFERENCES ocr_document_extraction (id) ON DELETE SET NULL,
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE ocr_suggestion (
                id BINARY(16) NOT NULL, extraction_id BINARY(16) NOT NULL, field_path VARCHAR(255) NOT NULL, label VARCHAR(255) NOT NULL, value_type VARCHAR(32) NOT NULL,
                raw_value JSON DEFAULT NULL, corrected_value JSON DEFAULT NULL, observed_value JSON DEFAULT NULL, confidence NUMERIC(5, 4) DEFAULT NULL, page_references JSON NOT NULL,
                status VARCHAR(16) NOT NULL, decided_by VARCHAR(180) DEFAULT NULL, decided_at DATETIME DEFAULT NULL,
                UNIQUE INDEX UNIQ_OCR_SUGGESTION_PATH (extraction_id, field_path),
                CONSTRAINT FK_OCR_SUGGESTION_EXTRACTION FOREIGN KEY (extraction_id) REFERENCES ocr_document_extraction (id) ON DELETE CASCADE,
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE ocr_suggestion');
        $this->addSql('DROP TABLE ocr_document_extraction');
    }
}
