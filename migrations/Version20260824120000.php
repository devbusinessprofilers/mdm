<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260824120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Ajoute pim_fiche_suggestion : suggestions d'enrichissement génériques à arbitrer (Sirene d'abord).";
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            <<<'SQL'
            CREATE TABLE pim_fiche_suggestion (
                id BINARY(16) NOT NULL COMMENT '(DC2Type:ulid)',
                fiche_id BINARY(16) NOT NULL COMMENT '(DC2Type:ulid)',
                source VARCHAR(32) NOT NULL,
                action VARCHAR(32) NOT NULL,
                champ VARCHAR(64) NOT NULL,
                label VARCHAR(255) NOT NULL,
                valeur_actuelle VARCHAR(500) DEFAULT NULL,
                valeur_proposee VARCHAR(500) DEFAULT NULL,
                score NUMERIC(5, 4) DEFAULT NULL,
                statut VARCHAR(16) NOT NULL,
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                decided_by VARCHAR(180) DEFAULT NULL,
                decided_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
                INDEX IDX_FICHE_SUGGESTION_ATTENTE (statut, fiche_id),
                UNIQUE INDEX UNIQ_FICHE_SUGGESTION_CLE (fiche_id, source, champ),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4
              COLLATE `utf8mb4_unicode_ci`
              ENGINE = InnoDB
            SQL
            ,
        );
        $this->addSql(
            'ALTER TABLE pim_fiche_suggestion ADD CONSTRAINT FK_FICHE_SUGGESTION_FICHE FOREIGN KEY (fiche_id) REFERENCES pim_fiche (id) ON DELETE CASCADE',
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE pim_fiche_suggestion');
    }
}
