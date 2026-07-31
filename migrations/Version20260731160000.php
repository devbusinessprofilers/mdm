<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Pim\Lov\ActiviteLovCatalog;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260731160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le domaine Activité normalisé et ses listes de valeurs.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE pim_ressource_lieu ADD fiche_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:ulid)\' AFTER lieu_id',
        );
        $this->addSql(
            'UPDATE pim_ressource_lieu r INNER JOIN pim_lieu l ON l.id=r.lieu_id SET r.fiche_id=l.fiche_id',
        );
        $this->addSql(
            'ALTER TABLE pim_ressource_lieu MODIFY fiche_id BINARY(16) NOT NULL COMMENT \'(DC2Type:ulid)\', MODIFY lieu_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:ulid)\'',
        );
        $this->addSql(
            'ALTER TABLE pim_ressource_lieu ADD CONSTRAINT FK_RESSOURCE_FICHE FOREIGN KEY (fiche_id) REFERENCES pim_fiche (id) ON DELETE CASCADE',
        );
        $this->addSql(
            'CREATE INDEX IDX_RESSOURCE_FICHE_ORDERED ON pim_ressource_lieu (fiche_id, position, id)',
        );
        $this->addSql(
            <<<'SQL'
                CREATE TABLE pim_activite (
                    id BINARY(16) NOT NULL COMMENT '(DC2Type:ulid)',
                    fiche_id BINARY(16) NOT NULL COMMENT '(DC2Type:ulid)',
                    prestataire_value_id INT UNSIGNED DEFAULT NULL,
                    mode_intervention VARCHAR(16) DEFAULT NULL,
                    toute_france TINYINT(1) DEFAULT 0 NOT NULL,
                    pays_mobiles JSON NOT NULL,
                    regions_mobiles JSON NOT NULL,
                    departements_mobiles JSON NOT NULL,
                    description_generale LONGTEXT DEFAULT NULL,
                    comprend_prestation LONGTEXT DEFAULT NULL,
                    participants_min INT DEFAULT NULL,
                    participants_max INT DEFAULT NULL,
                    duree_min_minutes INT DEFAULT NULL,
                    duree_max_minutes INT DEFAULT NULL,
                    plus JSON NOT NULL,
                    tarif_par_personne NUMERIC(12, 2) DEFAULT NULL,
                    youtube_url VARCHAR(255) DEFAULT NULL,
                    created_at DATETIME NOT NULL,
                    updated_at DATETIME NOT NULL,
                    UNIQUE INDEX UNIQ_ACTIVITE_FICHE (fiche_id),
                    INDEX IDX_ACTIVITE_PRESTATAIRE (prestataire_value_id),
                    PRIMARY KEY(id)
                ) DEFAULT CHARACTER SET utf8mb4
                  COLLATE `utf8mb4_unicode_ci`
                  ENGINE = InnoDB
                SQL,
        );
        $this->addSql(
            <<<'SQL'
                CREATE TABLE pim_activite_offre (
                    id BINARY(16) NOT NULL COMMENT '(DC2Type:ulid)',
                    activite_id BINARY(16) NOT NULL COMMENT '(DC2Type:ulid)',
                    type VARCHAR(16) NOT NULL,
                    nom VARCHAR(255) DEFAULT NULL,
                    participants_min INT DEFAULT NULL,
                    participants_max INT DEFAULT NULL,
                    prix NUMERIC(12, 2) DEFAULT NULL,
                    mode_tarification VARCHAR(20) NOT NULL,
                    position INT DEFAULT 0 NOT NULL,
                    created_at DATETIME NOT NULL,
                    updated_at DATETIME NOT NULL,
                    INDEX IDX_ACTIVITE_OFFRE_ORDERED (activite_id, type, position, id),
                    PRIMARY KEY(id)
                ) DEFAULT CHARACTER SET utf8mb4
                  COLLATE `utf8mb4_unicode_ci`
                  ENGINE = InnoDB
                SQL,
        );
        $this->addSql(
            'ALTER TABLE pim_activite ADD CONSTRAINT FK_ACTIVITE_FICHE FOREIGN KEY (fiche_id) REFERENCES pim_fiche (id) ON DELETE CASCADE',
        );
        $this->addSql(
            'ALTER TABLE pim_activite ADD CONSTRAINT FK_ACTIVITE_PRESTATAIRE FOREIGN KEY (prestataire_value_id) REFERENCES pim_attribute_value (id) ON DELETE RESTRICT',
        );
        $this->addSql(
            'ALTER TABLE pim_activite_offre ADD CONSTRAINT FK_ACTIVITE_OFFRE FOREIGN KEY (activite_id) REFERENCES pim_activite (id) ON DELETE CASCADE',
        );
        $this->seedAttribute('PRESTATAIRE', 'Prestataire');
        foreach (ActiviteLovCatalog::allChoices() as $code => $choices) {
            $this->seedAttribute(
                $code,
                match ($code) {
                    'TYPE_EXT_INT' => 'Type intérieur / extérieur',
                    'THEMATIQUE_ACTIVITE' => 'Thématique de l’activité',
                    'LANGUE_PARLEE' => 'Langue parlée',
                    'ENGAGEMENT_RSE' => 'Engagement RSE',
                    'OBJECTIF_SEMINAIRE' => 'Objectif de séminaire',
                    default => $code,
                },
            );
            $position = 0;
            foreach ($choices as $valueCode => $label) {
                $this->addSql(
                    'INSERT IGNORE INTO pim_attribute_value (id, attribute_id, code, label, position) VALUES (?, ?, ?, ?, ?)',
                    [
                        ActiviteLovCatalog::valueId($code, $valueCode),
                        ActiviteLovCatalog::attributeId($code),
                        $valueCode,
                        $label,
                        $position++,
                    ],
                );
            }
        }
    }

    public function down(Schema $schema): void
    {
        $codes = [
            'PRESTATAIRE',
            ...array_keys(ActiviteLovCatalog::allChoices()),
        ];
        $params = implode(',', array_fill(0, count($codes), '?'));
        $this->addSql("DELETE FROM pim_fiche WHERE type = 'activite'");
        $this->addSql('DROP TABLE pim_activite_offre');
        $this->addSql('DROP TABLE pim_activite');
        $this->addSql(
            'ALTER TABLE pim_ressource_lieu DROP FOREIGN KEY FK_RESSOURCE_FICHE',
        );
        $this->addSql(
            'DROP INDEX IDX_RESSOURCE_FICHE_ORDERED ON pim_ressource_lieu',
        );
        $this->addSql(
            'ALTER TABLE pim_ressource_lieu DROP fiche_id, MODIFY lieu_id BINARY(16) NOT NULL COMMENT \'(DC2Type:ulid)\'',
        );
        $this->addSql(
            "DELETE fav FROM pim_fiche_attribute_value fav WHERE fav.attribute_code IN ($params)",
            $codes,
        );
        $this->addSql(
            "DELETE v FROM pim_attribute_value v INNER JOIN pim_attribute_definition a ON a.id=v.attribute_id WHERE a.code IN ($params)",
            $codes,
        );
        $this->addSql(
            "DELETE FROM pim_attribute_definition WHERE code IN ($params)",
            $codes,
        );
    }

    private function seedAttribute(string $code, string $label): void
    {
        $id =
            'PRESTATAIRE' === $code
                ? (int) sprintf('%u', crc32('attribute:PRESTATAIRE'))
                : ActiviteLovCatalog::attributeId($code);
        $this->addSql(
            "INSERT IGNORE INTO pim_attribute_definition (id, code, label, completeness_weight, translatable, filterable, master_application) VALUES (?, ?, ?, 0, 0, 1, 'pim')",
            [$id, $code, $label],
        );
    }
}
