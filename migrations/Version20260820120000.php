<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Un média peut pointer vers une version retouchée acceptée : elle devient la
 * source des renditions sans jamais toucher à l'original ni à son checksum
 * (le dédoublonnage reste calé sur le fichier déposé).
 */
final class Version20260820120000 extends AbstractMigration
{
    public function getDescription(): string { return 'Ajoute la source retouchée (clé, checksum, date) sur les médias DAM.'; }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE dam_media_asset
                ADD enhanced_storage_key VARCHAR(1024) DEFAULT NULL,
                ADD enhanced_checksum VARCHAR(64) DEFAULT NULL,
                ADD enhanced_at DATETIME DEFAULT NULL
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE dam_media_asset DROP enhanced_storage_key, DROP enhanced_checksum, DROP enhanced_at');
    }
}
