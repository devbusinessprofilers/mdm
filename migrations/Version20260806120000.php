<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260806120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Tables de correspondance de l\'import legacy (fiches syspad et photos).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE etl_legacy_fiche (
                syspad_id INT UNSIGNED NOT NULL,
                fiche_id BINARY(16) NOT NULL COMMENT '(DC2Type:ulid)',
                label VARCHAR(255) DEFAULT NULL,
                gamme VARCHAR(32) NOT NULL,
                photos_json LONGTEXT DEFAULT NULL,
                photos_seeded_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                UNIQUE INDEX UNIQ_ETL_LEGACY_FICHE_FICHE (fiche_id),
                PRIMARY KEY (syspad_id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE etl_legacy_photo (
                id INT UNSIGNED AUTO_INCREMENT NOT NULL,
                syspad_id INT UNSIGNED NOT NULL,
                legacy_path VARCHAR(500) NOT NULL,
                category VARCHAR(32) NOT NULL,
                usage_code VARCHAR(32) NOT NULL,
                position SMALLINT UNSIGNED NOT NULL,
                status VARCHAR(16) NOT NULL,
                media_asset_id VARCHAR(26) DEFAULT NULL,
                error_message LONGTEXT DEFAULT NULL,
                attempts SMALLINT UNSIGNED DEFAULT 0 NOT NULL,
                processed_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                UNIQUE INDEX UNIQ_ETL_LEGACY_PHOTO_PATH (syspad_id, legacy_path),
                INDEX IDX_ETL_LEGACY_PHOTO_STATUS (status, syspad_id),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE etl_legacy_photo');
        $this->addSql('DROP TABLE etl_legacy_fiche');
    }
}
