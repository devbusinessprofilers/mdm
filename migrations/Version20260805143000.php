<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260805143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute pHash, alertes de doublons, mots-clés et validité des droits DAM.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE dam_media_asset ADD perceptual_hash VARCHAR(16) DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_DAM_MEDIA_PHASH ON dam_media_asset (perceptual_hash)');
        $this->addSql('ALTER TABLE pim_ressource_lieu ADD keywords LONGTEXT DEFAULT NULL, ADD rights_expires_at DATE DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_PIM_RESOURCE_RIGHTS_EXPIRY ON pim_ressource_lieu (rights_granted, rights_expires_at)');
        $this->addSql("CREATE TABLE dam_media_phash_band (media_id BINARY(16) NOT NULL, band_index SMALLINT NOT NULL, band_value SMALLINT NOT NULL, PRIMARY KEY(media_id, band_index), INDEX IDX_DAM_PHASH_BAND_LOOKUP (band_index, band_value), CONSTRAINT FK_DAM_PHASH_BAND_MEDIA FOREIGN KEY (media_id) REFERENCES dam_media_asset (id) ON DELETE CASCADE) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql("CREATE TABLE dam_media_duplicate_alert (id BINARY(16) NOT NULL, media_id BINARY(16) NOT NULL, duplicate_of_id BINARY(16) NOT NULL, resource_id VARCHAR(26) NOT NULL, fiche_id VARCHAR(26) NOT NULL, fiche_type VARCHAR(32) NOT NULL, kind VARCHAR(16) NOT NULL, distance INT DEFAULT NULL, status VARCHAR(16) NOT NULL, reviewed_by VARCHAR(26) DEFAULT NULL, reviewed_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_DAM_DUPLICATE_MEDIA (media_id), INDEX IDX_DAM_DUPLICATE_STATUS_CREATED (status, created_at), INDEX IDX_DAM_DUPLICATE_FICHE_TYPE (fiche_type, status), INDEX IDX_DAM_DUPLICATE_REFERENCE (duplicate_of_id), PRIMARY KEY(id), CONSTRAINT FK_DAM_DUPLICATE_MEDIA FOREIGN KEY (media_id) REFERENCES dam_media_asset (id) ON DELETE CASCADE, CONSTRAINT FK_DAM_DUPLICATE_REFERENCE FOREIGN KEY (duplicate_of_id) REFERENCES dam_media_asset (id) ON DELETE CASCADE) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE dam_media_duplicate_alert');
        $this->addSql('DROP TABLE dam_media_phash_band');
        $this->addSql('DROP INDEX IDX_DAM_MEDIA_PHASH ON dam_media_asset');
        $this->addSql('ALTER TABLE dam_media_asset DROP perceptual_hash');
        $this->addSql('DROP INDEX IDX_PIM_RESOURCE_RIGHTS_EXPIRY ON pim_ressource_lieu');
        $this->addSql('ALTER TABLE pim_ressource_lieu DROP keywords, DROP rights_expires_at');
    }
}
