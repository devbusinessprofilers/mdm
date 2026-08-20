<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260820122000 extends AbstractMigration
{
    public function getDescription(): string { return 'Ajoute les reconnaissances IA d\'images et leurs suggestions révisables.'; }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE vision_image_recognition (
                id BINARY(16) NOT NULL, fiche_id BINARY(16) NOT NULL, media_asset_id BINARY(16) NOT NULL, resource_id BINARY(16) NOT NULL,
                source_checksum VARCHAR(64) NOT NULL, prompt LONGTEXT NOT NULL, provider_model VARCHAR(100) NOT NULL, status VARCHAR(32) NOT NULL,
                created_by VARCHAR(180) NOT NULL, attempts SMALLINT UNSIGNED DEFAULT 0 NOT NULL,
                started_at DATETIME DEFAULT NULL, finished_at DATETIME DEFAULT NULL, error_message LONGTEXT DEFAULT NULL, raw_response JSON DEFAULT NULL,
                created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
                INDEX IDX_VISION_RECOGNITION_FICHE_CREATED (fiche_id, created_at), INDEX IDX_VISION_RECOGNITION_STATUS (status, updated_at), INDEX IDX_VISION_RECOGNITION_MEDIA (media_asset_id), INDEX IDX_VISION_RECOGNITION_RESOURCE (resource_id),
                CONSTRAINT FK_VISION_RECOGNITION_FICHE FOREIGN KEY (fiche_id) REFERENCES pim_fiche (id) ON DELETE CASCADE,
                CONSTRAINT FK_VISION_RECOGNITION_MEDIA FOREIGN KEY (media_asset_id) REFERENCES dam_media_asset (id) ON DELETE RESTRICT,
                CONSTRAINT FK_VISION_RECOGNITION_RESOURCE FOREIGN KEY (resource_id) REFERENCES pim_ressource_lieu (id) ON DELETE CASCADE,
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE vision_image_recognition_suggestion (
                id BINARY(16) NOT NULL, recognition_id BINARY(16) NOT NULL, field_path VARCHAR(255) NOT NULL, label VARCHAR(255) NOT NULL,
                raw_value JSON DEFAULT NULL, corrected_value JSON DEFAULT NULL, observed_value JSON DEFAULT NULL,
                status VARCHAR(16) NOT NULL, decided_by VARCHAR(180) DEFAULT NULL, decided_at DATETIME DEFAULT NULL,
                UNIQUE INDEX UNIQ_VISION_RECO_SUGGESTION_PATH (recognition_id, field_path),
                CONSTRAINT FK_VISION_RECO_SUGGESTION_RECOGNITION FOREIGN KEY (recognition_id) REFERENCES vision_image_recognition (id) ON DELETE CASCADE,
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE vision_image_recognition_suggestion');
        $this->addSql('DROP TABLE vision_image_recognition');
    }
}
