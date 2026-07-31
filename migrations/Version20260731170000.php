<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260731170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Aligne les colonnes ULID et index Activité sur le mapping Doctrine.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE pim_ressource_lieu CHANGE lieu_id lieu_id BINARY(16) DEFAULT NULL, CHANGE fiche_id fiche_id BINARY(16) NOT NULL',
        );
        $this->addSql(
            'ALTER TABLE pim_activite CHANGE id id BINARY(16) NOT NULL, CHANGE fiche_id fiche_id BINARY(16) NOT NULL',
        );
        $this->addSql(
            'ALTER TABLE pim_activite RENAME INDEX uniq_activite_fiche TO UNIQ_20A3643DDF522508',
        );
        $this->addSql(
            'ALTER TABLE pim_activite RENAME INDEX idx_activite_prestataire TO IDX_20A3643D3DD49DD1',
        );
        $this->addSql(
            'ALTER TABLE pim_activite_offre CHANGE id id BINARY(16) NOT NULL, CHANGE activite_id activite_id BINARY(16) NOT NULL',
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            "ALTER TABLE pim_ressource_lieu CHANGE lieu_id lieu_id BINARY(16) DEFAULT NULL COMMENT '(DC2Type:ulid)', CHANGE fiche_id fiche_id BINARY(16) NOT NULL COMMENT '(DC2Type:ulid)'",
        );
        $this->addSql(
            "ALTER TABLE pim_activite CHANGE id id BINARY(16) NOT NULL COMMENT '(DC2Type:ulid)', CHANGE fiche_id fiche_id BINARY(16) NOT NULL COMMENT '(DC2Type:ulid)'",
        );
        $this->addSql(
            'ALTER TABLE pim_activite RENAME INDEX UNIQ_20A3643DDF522508 TO uniq_activite_fiche',
        );
        $this->addSql(
            'ALTER TABLE pim_activite RENAME INDEX IDX_20A3643D3DD49DD1 TO idx_activite_prestataire',
        );
        $this->addSql(
            "ALTER TABLE pim_activite_offre CHANGE id id BINARY(16) NOT NULL COMMENT '(DC2Type:ulid)', CHANGE activite_id activite_id BINARY(16) NOT NULL COMMENT '(DC2Type:ulid)'",
        );
    }
}
