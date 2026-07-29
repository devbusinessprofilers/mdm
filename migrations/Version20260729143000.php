<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260729143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Structure les salles, fermetures, accès et ressources DAM du lieu en collections.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE pim_acces_lieu (id VARCHAR(26) NOT NULL, type VARCHAR(32) NOT NULL, nom VARCHAR(255) NOT NULL, distance_kilometres NUMERIC(8, 2) DEFAULT NULL, duree_minutes INT DEFAULT NULL, mode_transport VARCHAR(255) DEFAULT NULL, position INT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, lieu_id VARCHAR(26) NOT NULL, INDEX IDX_ACECD356AB213CC (lieu_id), INDEX IDX_PIM_ACCES_TYPE (type), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE pim_salle (id VARCHAR(26) NOT NULL, nom VARCHAR(255) NOT NULL, superficie INT DEFAULT NULL, capacite_reunion INT DEFAULT NULL, capacite_u INT DEFAULT NULL, capacite_classe INT DEFAULT NULL, capacite_theatre INT DEFAULT NULL, capacite_cabaret INT DEFAULT NULL, capacite_banquet INT DEFAULT NULL, capacite_cocktail INT DEFAULT NULL, capacite_auditorium INT DEFAULT NULL, lumiere_jour TINYINT DEFAULT 0 NOT NULL, acces_pmr TINYINT DEFAULT 0 NOT NULL, espace_dansant TINYINT DEFAULT 0 NOT NULL, climatisee TINYINT DEFAULT 0 NOT NULL, position INT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, lieu_id VARCHAR(26) NOT NULL, INDEX IDX_460272AD6AB213CC (lieu_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE pim_ressource_lieu (id VARCHAR(26) NOT NULL, dam_asset_id VARCHAR(255) NOT NULL, nature VARCHAR(32) NOT NULL, usage_code VARCHAR(64) NOT NULL, legende VARCHAR(255) DEFAULT NULL, position INT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, lieu_id VARCHAR(26) NOT NULL, salle_id VARCHAR(26) DEFAULT NULL, INDEX IDX_586964B06AB213CC (lieu_id), INDEX IDX_586964B0DC304035 (salle_id), INDEX IDX_PIM_RESSOURCE_USAGE (usage_code), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE pim_periode_fermeture (id VARCHAR(26) NOT NULL, nom VARCHAR(255) NOT NULL, date_debut DATE NOT NULL, date_fin DATE NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, lieu_id VARCHAR(26) NOT NULL, INDEX IDX_FFBA7A386AB213CC (lieu_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE pim_acces_lieu ADD CONSTRAINT FK_ACECD356AB213CC FOREIGN KEY (lieu_id) REFERENCES pim_lieu (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE pim_salle ADD CONSTRAINT FK_460272AD6AB213CC FOREIGN KEY (lieu_id) REFERENCES pim_lieu (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE pim_ressource_lieu ADD CONSTRAINT FK_586964B06AB213CC FOREIGN KEY (lieu_id) REFERENCES pim_lieu (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE pim_ressource_lieu ADD CONSTRAINT FK_586964B0DC304035 FOREIGN KEY (salle_id) REFERENCES pim_salle (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE pim_periode_fermeture ADD CONSTRAINT FK_FFBA7A386AB213CC FOREIGN KEY (lieu_id) REFERENCES pim_lieu (id) ON DELETE CASCADE');

        $this->migrateLegacyData();

        $this->addSql('ALTER TABLE pim_lieu DROP dispo_nom_periode, DROP dispo_date_fermeture_debut, DROP dispo_date_fermeture_fin, DROP access_aeroport, DROP access_gare, DROP access_metro, DROP access_tramway, DROP access_grande_ville, DROP desc_generale_point_interet, DROP config_superficie, DROP config_nom_salle, DROP config_superficie_reunion, DROP config_superficie_u, DROP config_superficie_classe, DROP config_superficie_theatre, DROP config_superficie_cabaret, DROP config_superficie_banquet, DROP config_superficie_cocktail, DROP config_supericie_auditorium, DROP config_lumiere_jour, DROP config_acces_pmr, DROP config_dansant, DROP config_climatisee, DROP config_plan_salle, DROP config_photo_salle, DROP rse_desc_generale, DROP loisir_externe_photo, DROP photo, DROP pj_plan_general, DROP pj_support_commerciaux, DROP info_legale_attestation_vigilance_urssaf, DROP info_legale_assurance_rc_pro, DROP mode_paiement_rib, DROP affacturage_rib, DROP cond_gen_vente_depot_doc, DROP conv_part_fichier');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('La remise à plat des collections ferait perdre les entrées multiples.');
    }

    private function migrateLegacyData(): void
    {
        $id = "LOWER(LEFT(REPLACE(UUID(), '-', ''), 26))";

        $this->addSql("INSERT INTO pim_periode_fermeture (id, lieu_id, nom, date_debut, date_fin, created_at, updated_at) SELECT {$id}, id, COALESCE(NULLIF(dispo_nom_periode, ''), 'Fermeture'), dispo_date_fermeture_debut, dispo_date_fermeture_fin, NOW(), NOW() FROM pim_lieu WHERE dispo_date_fermeture_debut IS NOT NULL AND dispo_date_fermeture_fin IS NOT NULL");
        $this->addSql("INSERT INTO pim_salle (id, lieu_id, nom, superficie, capacite_reunion, capacite_u, capacite_classe, capacite_theatre, capacite_cabaret, capacite_banquet, capacite_cocktail, capacite_auditorium, lumiere_jour, acces_pmr, espace_dansant, climatisee, position, created_at, updated_at) SELECT {$id}, id, COALESCE(NULLIF(config_nom_salle, ''), 'Salle'), config_superficie, config_superficie_reunion, config_superficie_u, config_superficie_classe, config_superficie_theatre, config_superficie_cabaret, config_superficie_banquet, config_superficie_cocktail, config_supericie_auditorium, COALESCE(config_lumiere_jour, 0), COALESCE(config_acces_pmr, 0), COALESCE(config_dansant, 0), COALESCE(config_climatisee, 0), 0, NOW(), NOW() FROM pim_lieu WHERE config_nom_salle IS NOT NULL OR config_superficie IS NOT NULL");

        foreach (['access_aeroport' => 'aeroport', 'access_gare' => 'gare', 'access_metro' => 'metro', 'access_tramway' => 'tramway', 'access_grande_ville' => 'grande_ville', 'desc_generale_point_interet' => 'point_interet'] as $column => $type) {
            $this->addSql("INSERT INTO pim_acces_lieu (id, lieu_id, type, nom, position, created_at, updated_at) SELECT {$id}, l.id, '{$type}', j.nom, j.position - 1, NOW(), NOW() FROM pim_lieu l JOIN JSON_TABLE(l.{$column}, '$[*]' COLUMNS (position FOR ORDINALITY, nom VARCHAR(255) PATH '$')) j WHERE j.nom <> ''");
        }

        $this->addSql("INSERT INTO pim_ressource_lieu (id, lieu_id, dam_asset_id, nature, usage_code, position, created_at, updated_at) SELECT {$id}, l.id, j.asset_id, 'photo', 'galerie', j.position - 1, NOW(), NOW() FROM pim_lieu l JOIN JSON_TABLE(l.photo, '$[*]' COLUMNS (position FOR ORDINALITY, asset_id VARCHAR(255) PATH '$')) j WHERE j.asset_id <> ''");
        $this->addSql("INSERT INTO pim_ressource_lieu (id, lieu_id, dam_asset_id, nature, usage_code, position, created_at, updated_at) SELECT {$id}, l.id, j.asset_id, 'photo', 'loisir_externe', j.position - 1, NOW(), NOW() FROM pim_lieu l JOIN JSON_TABLE(l.loisir_externe_photo, '$[*]' COLUMNS (position FOR ORDINALITY, asset_id VARCHAR(255) PATH '$')) j WHERE j.asset_id <> ''");

        foreach (['pj_plan_general' => 'plan_general', 'pj_support_commerciaux' => 'support_commercial', 'rse_desc_generale' => 'rse', 'info_legale_attestation_vigilance_urssaf' => 'attestation_urssaf', 'info_legale_assurance_rc_pro' => 'assurance_rc', 'mode_paiement_rib' => 'rib', 'affacturage_rib' => 'rib_affacturage', 'cond_gen_vente_depot_doc' => 'conditions_generales_vente', 'conv_part_fichier' => 'convention_partenaire'] as $column => $usage) {
            $this->addSql("INSERT INTO pim_ressource_lieu (id, lieu_id, dam_asset_id, nature, usage_code, position, created_at, updated_at) SELECT {$id}, id, {$column}, 'document', '{$usage}', 0, NOW(), NOW() FROM pim_lieu WHERE {$column} IS NOT NULL AND {$column} <> ''");
        }

        foreach (['config_plan_salle' => ['document', 'plan_salle'], 'config_photo_salle' => ['photo', 'photo_salle']] as $column => [$nature, $usage]) {
            $this->addSql("INSERT INTO pim_ressource_lieu (id, lieu_id, salle_id, dam_asset_id, nature, usage_code, position, created_at, updated_at) SELECT {$id}, l.id, s.id, l.{$column}, '{$nature}', '{$usage}', 0, NOW(), NOW() FROM pim_lieu l JOIN pim_salle s ON s.lieu_id = l.id WHERE l.{$column} IS NOT NULL AND l.{$column} <> ''");
        }
    }
}
