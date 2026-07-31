<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Pim\Lov\ServiceLovCatalog;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260731180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Ajoute le domaine Service événementiel et sa liste contrôlée de prestations.";
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            <<<'SQL'
            CREATE TABLE pim_service_evenementiel (
                id BINARY(16) NOT NULL,
                fiche_id BINARY(16) NOT NULL,
                description_generale LONGTEXT DEFAULT NULL,
                prestataire_esat TINYINT(1) DEFAULT NULL,
                demarche_rse TINYINT(1) DEFAULT NULL,
                adapte_femmes_enceintes TINYINT(1) DEFAULT NULL,
                adapte_malentendants TINYINT(1) DEFAULT NULL,
                adapte_malvoyants TINYINT(1) DEFAULT NULL,
                materiel_inclus TINYINT(1) DEFAULT NULL,
                equipement_participants_requis TINYINT(1) DEFAULT NULL,
                equipement_reception_requis TINYINT(1) DEFAULT NULL,
                contraintes_logistiques TINYINT(1) DEFAULT NULL,
                mode_intervention VARCHAR(16) DEFAULT NULL,
                pays_mobiles JSON NOT NULL,
                regions_mobiles JSON NOT NULL,
                departements_mobiles JSON NOT NULL,
                tarif_par_prestation DOUBLE PRECISION DEFAULT NULL,
                tarif_par_personne DOUBLE PRECISION DEFAULT NULL,
                tarif_par_jour DOUBLE PRECISION DEFAULT NULL,
                tarif_par_demi_journee DOUBLE PRECISION DEFAULT NULL,
                tarif_par_heure DOUBLE PRECISION DEFAULT NULL,
                sur_devis TINYINT(1) DEFAULT NULL,
                youtube_url VARCHAR(255) DEFAULT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE INDEX UNIQ_SERVICE_FICHE (fiche_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4
              COLLATE `utf8mb4_unicode_ci`
              ENGINE = InnoDB
            SQL
            ,
        );
        $this->addSql(
            "ALTER TABLE pim_service_evenementiel ADD CONSTRAINT FK_SERVICE_FICHE FOREIGN KEY (fiche_id) REFERENCES pim_fiche (id) ON DELETE CASCADE",
        );

        $this->addSql(
            "INSERT IGNORE INTO pim_attribute_definition (id, code, label, completeness_weight, translatable, filterable, master_application) VALUES (?, 'TYPE_PRESTATAIRE', 'Prestations', 0, 1, 1, 'pim')",
            [ServiceLovCatalog::attributeId()],
        );
        $position = 0;
        foreach (ServiceLovCatalog::prestations() as $code => $label) {
            $this->addSql(
                "INSERT IGNORE INTO pim_attribute_value (id, attribute_id, code, label, position) VALUES (?, ?, ?, ?, ?)",
                [
                    ServiceLovCatalog::valueId($code),
                    ServiceLovCatalog::attributeId(),
                    $code,
                    $label,
                    $position++,
                ],
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            "DELETE FROM pim_fiche WHERE type = 'service_evenementiel'",
        );
        $this->addSql("DROP TABLE pim_service_evenementiel");
        $this->addSql(
            "DELETE FROM pim_fiche_attribute_value WHERE attribute_code = 'TYPE_PRESTATAIRE'",
        );
        $this->addSql(
            "DELETE FROM pim_attribute_value WHERE attribute_id = ?",
            [ServiceLovCatalog::attributeId()],
        );
        $this->addSql("DELETE FROM pim_attribute_definition WHERE id = ?", [
            ServiceLovCatalog::attributeId(),
        ]);
    }
}
