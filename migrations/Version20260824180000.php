<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260824180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Ajoute pim_fiche_enrichment_scan : trace le dernier scan d'une source par fiche (batch incrémental).";
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            <<<'SQL'
            CREATE TABLE pim_fiche_enrichment_scan (
                fiche_id BINARY(16) NOT NULL COMMENT '(DC2Type:ulid)',
                source VARCHAR(32) NOT NULL,
                scanned_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                INDEX IDX_FICHE_SCAN_SOURCE (source, scanned_at),
                PRIMARY KEY(fiche_id, source)
            ) DEFAULT CHARACTER SET utf8mb4
              COLLATE `utf8mb4_unicode_ci`
              ENGINE = InnoDB
            SQL
            ,
        );
        $this->addSql(
            'ALTER TABLE pim_fiche_enrichment_scan ADD CONSTRAINT FK_FICHE_SCAN_FICHE FOREIGN KEY (fiche_id) REFERENCES pim_fiche (id) ON DELETE CASCADE',
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE pim_fiche_enrichment_scan');
    }
}
