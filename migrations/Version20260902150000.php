<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Journal des traitements : historique des attributions géographiques de
 * visibilité (commande de rattrapage, création, bouton « Appliquer les sites
 * automatiques ») + index de tri pour la nouvelle famille « Diffusion
 * marketplace » du journal (tous statuts, plus seulement les échecs).
 */
final class Version20260902150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add pim_visibilite_geo_run journal table and etl_fiche_marketplace updated_at index.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE pim_visibilite_geo_run (id BINARY(16) NOT NULL COMMENT '(DC2Type:ulid)', fiche_id BINARY(16) DEFAULT NULL COMMENT '(DC2Type:ulid)', declencheur VARCHAR(16) NOT NULL, nb_fiches INT UNSIGNED NOT NULL, nb_attributions INT UNSIGNED NOT NULL, detail JSON DEFAULT NULL, executed_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_VISIBILITE_GEO_RUN_EXECUTED (executed_at), INDEX IDX_VISIBILITE_GEO_RUN_FICHE (fiche_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`");
        $this->addSql('ALTER TABLE pim_visibilite_geo_run ADD CONSTRAINT FK_VISIBILITE_GEO_RUN_FICHE FOREIGN KEY (fiche_id) REFERENCES pim_fiche (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_ETL_FICHE_MARKETPLACE_UPDATED ON etl_fiche_marketplace (updated_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_ETL_FICHE_MARKETPLACE_UPDATED ON etl_fiche_marketplace');
        $this->addSql('DROP TABLE pim_visibilite_geo_run');
    }
}
