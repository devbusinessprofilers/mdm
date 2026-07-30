<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260730143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le registre DAM des originaux stockés sur OVH S3.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE dam_media_asset (id BINARY(16) NOT NULL, original_storage_key VARCHAR(1024) NOT NULL, original_filename VARCHAR(255) NOT NULL, mime_type VARCHAR(100) NOT NULL, size_bytes BIGINT NOT NULL, checksum VARCHAR(64) NOT NULL, status VARCHAR(32) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_7F421D64E618758F (original_storage_key), INDEX IDX_DAM_MEDIA_CHECKSUM (checksum), INDEX IDX_DAM_MEDIA_STATUS_CREATED (status, created_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE dam_media_asset');
    }
}
