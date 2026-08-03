<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260803150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la configuration et les scores de complétude par canal, avec un worker dédié.';
    }

    public function up(Schema $schema): void
    {
        $tooLong = (int) $this->connection->fetchOne(<<<'SQL'
            SELECT COUNT(*) FROM pim_lieu
            WHERE CHAR_LENGTH(generale_website_url) > 100
               OR CHAR_LENGTH(pmr_details) > 150
               OR CHAR_LENGTH(desc_generale) > 1000
               OR CHAR_LENGTH(chambre_desc_generale) > 1000
               OR CHAR_LENGTH(atout_1) > 35
               OR CHAR_LENGTH(atout_2) > 35
               OR CHAR_LENGTH(atout_3) > 35
               OR CHAR_LENGTH(atout_4) > 35
               OR CHAR_LENGTH(atout_5) > 35
            SQL);
        $this->abortIf(0 < $tooLong, sprintf('%d lieu(x) contiennent une valeur dépassant les limites de la Bible. Corriger ces données avant de relancer la migration.', $tooLong));

        $this->addSql(<<<'SQL'
            CREATE TABLE pim_completeness_field_configuration (
                id INT UNSIGNED AUTO_INCREMENT NOT NULL,
                fiche_type VARCHAR(32) NOT NULL,
                field_code VARCHAR(96) NOT NULL,
                label VARCHAR(255) NOT NULL,
                formula VARCHAR(32) NOT NULL,
                weight NUMERIC(8, 2) DEFAULT 1.00 NOT NULL,
                target_length_override INT UNSIGNED DEFAULT NULL,
                active TINYINT(1) DEFAULT 1 NOT NULL,
                marketplace TINYINT(1) DEFAULT 1 NOT NULL,
                thematic_sites TINYINT(1) DEFAULT 1 NOT NULL,
                salesforce TINYINT(1) DEFAULT 1 NOT NULL,
                provider_portal TINYINT(1) DEFAULT 1 NOT NULL,
                UNIQUE INDEX UNIQ_COMPLETENESS_TYPE_CODE (fiche_type, field_code),
                INDEX IDX_COMPLETENESS_TYPE_ACTIVE (fiche_type, active),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE pim_completeness_configuration_revision (
                fiche_type VARCHAR(32) NOT NULL,
                revision INT UNSIGNED DEFAULT 1 NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY(fiche_type)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);
        $this->addSql("INSERT INTO pim_completeness_configuration_revision (fiche_type, revision, updated_at) VALUES ('lieu', 1, NOW()), ('activite', 1, NOW()), ('restaurant', 1, NOW()), ('service_evenementiel', 1, NOW())");

        foreach (['pim_lieu', 'pim_activite', 'pim_restaurant', 'pim_service_evenementiel'] as $table) {
            $this->addSql(sprintf(<<<'SQL'
                ALTER TABLE %s
                    ADD completeness_global SMALLINT UNSIGNED DEFAULT 0 NOT NULL,
                    ADD completeness_marketplace SMALLINT UNSIGNED DEFAULT 0 NOT NULL,
                    ADD completeness_thematic_sites SMALLINT UNSIGNED DEFAULT 0 NOT NULL,
                    ADD completeness_salesforce SMALLINT UNSIGNED DEFAULT 0 NOT NULL,
                    ADD completeness_provider_portal SMALLINT UNSIGNED DEFAULT 0 NOT NULL,
                    ADD completeness_calculated_at DATETIME DEFAULT NULL,
                    ADD completeness_revision INT UNSIGNED DEFAULT 0 NOT NULL,
                    ADD INDEX IDX_%s_COMPLETENESS_REVISION (completeness_revision)
                SQL, $table, strtoupper(str_replace('pim_', '', $table))));
        }

        $this->addSql('UPDATE pim_lieu d INNER JOIN pim_fiche f ON f.id = d.fiche_id SET d.completeness_global = f.completeness');
        $this->addSql('UPDATE pim_activite d INNER JOIN pim_fiche f ON f.id = d.fiche_id SET d.completeness_global = f.completeness');
        $this->addSql('UPDATE pim_restaurant d INNER JOIN pim_fiche f ON f.id = d.fiche_id SET d.completeness_global = f.completeness');
        $this->addSql('UPDATE pim_service_evenementiel d INNER JOIN pim_fiche f ON f.id = d.fiche_id SET d.completeness_global = f.completeness');

        $this->addSql('ALTER TABLE pim_fiche DROP completeness');
        $this->addSql('ALTER TABLE pim_attribute_definition DROP completeness_weight');
        $this->addSql(<<<'SQL'
            ALTER TABLE pim_lieu
                CHANGE generale_website_url generale_website_url VARCHAR(100) DEFAULT NULL,
                CHANGE pmr_details pmr_details VARCHAR(150) DEFAULT NULL,
                CHANGE desc_generale desc_generale VARCHAR(1000) DEFAULT NULL,
                CHANGE chambre_desc_generale chambre_desc_generale VARCHAR(1000) DEFAULT NULL,
                CHANGE atout_1 atout_1 VARCHAR(35) DEFAULT NULL,
                CHANGE atout_2 atout_2 VARCHAR(35) DEFAULT NULL,
                CHANGE atout_3 atout_3 VARCHAR(35) DEFAULT NULL,
                CHANGE atout_4 atout_4 VARCHAR(35) DEFAULT NULL,
                CHANGE atout_5 atout_5 VARCHAR(35) DEFAULT NULL
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pim_fiche ADD completeness SMALLINT UNSIGNED DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE pim_attribute_definition ADD completeness_weight SMALLINT UNSIGNED DEFAULT 0 NOT NULL');
        $this->addSql('UPDATE pim_fiche f INNER JOIN pim_lieu d ON f.id = d.fiche_id SET f.completeness = d.completeness_global');
        $this->addSql('UPDATE pim_fiche f INNER JOIN pim_activite d ON f.id = d.fiche_id SET f.completeness = d.completeness_global');
        $this->addSql('UPDATE pim_fiche f INNER JOIN pim_restaurant d ON f.id = d.fiche_id SET f.completeness = d.completeness_global');
        $this->addSql('UPDATE pim_fiche f INNER JOIN pim_service_evenementiel d ON f.id = d.fiche_id SET f.completeness = d.completeness_global');
        foreach (['pim_lieu', 'pim_activite', 'pim_restaurant', 'pim_service_evenementiel'] as $table) {
            $this->addSql(sprintf('ALTER TABLE %s DROP completeness_global, DROP completeness_marketplace, DROP completeness_thematic_sites, DROP completeness_salesforce, DROP completeness_provider_portal, DROP completeness_calculated_at, DROP completeness_revision', $table));
        }
        $this->addSql('DROP TABLE pim_completeness_field_configuration');
        $this->addSql('DROP TABLE pim_completeness_configuration_revision');
        $this->addSql(<<<'SQL'
            ALTER TABLE pim_lieu
                CHANGE generale_website_url generale_website_url VARCHAR(255) DEFAULT NULL,
                CHANGE pmr_details pmr_details LONGTEXT DEFAULT NULL,
                CHANGE desc_generale desc_generale LONGTEXT DEFAULT NULL,
                CHANGE chambre_desc_generale chambre_desc_generale LONGTEXT DEFAULT NULL,
                CHANGE atout_1 atout_1 VARCHAR(255) DEFAULT NULL,
                CHANGE atout_2 atout_2 VARCHAR(255) DEFAULT NULL,
                CHANGE atout_3 atout_3 VARCHAR(255) DEFAULT NULL,
                CHANGE atout_4 atout_4 VARCHAR(255) DEFAULT NULL,
                CHANGE atout_5 atout_5 VARCHAR(255) DEFAULT NULL
            SQL);
    }
}
