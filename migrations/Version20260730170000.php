<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260730170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le cycle de traitement DAM, les rendus et les métadonnées photo des lieux.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE dam_media_asset ADD error_message LONGTEXT DEFAULT NULL, ADD processed_at DATETIME DEFAULT NULL, ADD deleted_at DATETIME DEFAULT NULL');
        $this->addSql("CREATE TABLE dam_media_rendition (id BINARY(16) NOT NULL, media_id BINARY(16) NOT NULL, name VARCHAR(32) NOT NULL, storage_key VARCHAR(1024) NOT NULL, mime_type VARCHAR(100) NOT NULL, width INT NOT NULL, height INT NOT NULL, size_bytes BIGINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_DAM_RENDITION_MEDIA (media_id), UNIQUE INDEX UNIQ_DAM_RENDITION_MEDIA_NAME (media_id, name), UNIQUE INDEX UNIQ_DAM_RENDITION_STORAGE_KEY (storage_key), PRIMARY KEY(id), CONSTRAINT FK_DAM_RENDITION_MEDIA FOREIGN KEY (media_id) REFERENCES dam_media_asset (id) ON DELETE CASCADE) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE pim_ressource_lieu ADD rights_granted TINYINT(1) DEFAULT 0 NOT NULL, ADD rights_granted_at DATETIME DEFAULT NULL, ADD rights_granted_by VARCHAR(26) DEFAULT NULL, ADD source VARCHAR(255) DEFAULT NULL, ADD crop_x INT DEFAULT NULL, ADD crop_y INT DEFAULT NULL, ADD crop_width INT DEFAULT NULL, ADD crop_height INT DEFAULT NULL, ADD rotation INT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE dam_media_rendition');
        $this->addSql('ALTER TABLE dam_media_asset DROP error_message, DROP processed_at, DROP deleted_at');
        $this->addSql('ALTER TABLE pim_ressource_lieu DROP rights_granted, DROP rights_granted_at, DROP rights_granted_by, DROP source, DROP crop_x, DROP crop_y, DROP crop_width, DROP crop_height, DROP rotation');
    }
}
