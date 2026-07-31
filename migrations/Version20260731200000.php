<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Pim\Lov\RestaurantLovCatalog;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260731200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le domaine Restaurant, ses collections et ses listes contrôlées.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            <<<'SQL'
            CREATE TABLE pim_restaurant (
                id BINARY(16) NOT NULL,
                fiche_id BINARY(16) NOT NULL,
                site_officiel VARCHAR(100) DEFAULT NULL,
                privatisation_totale TINYINT(1) DEFAULT NULL,
                privatisation_partielle TINYINT(1) DEFAULT NULL,
                heure_ouverture TIME DEFAULT NULL,
                heure_fermeture TIME DEFAULT NULL,
                acces_pmr TINYINT(1) DEFAULT NULL,
                toilettes_pmr TINYINT(1) DEFAULT NULL,
                description_generale LONGTEXT DEFAULT NULL,
                atouts JSON NOT NULL,
                capacite_assise_max INT DEFAULT NULL,
                capacite_espace_privatisable INT DEFAULT NULL,
                capacite_banquet INT DEFAULT NULL,
                capacite_cocktail INT DEFAULT NULL,
                youtube_url VARCHAR(255) DEFAULT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE INDEX UNIQ_RESTAURANT_FICHE (fiche_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4
              COLLATE `utf8mb4_unicode_ci`
              ENGINE = InnoDB
            SQL,
        );
        $this->addSql(
            'ALTER TABLE pim_restaurant ADD CONSTRAINT FK_RESTAURANT_FICHE FOREIGN KEY (fiche_id) REFERENCES pim_fiche (id) ON DELETE CASCADE',
        );

        $this->addSql(
            <<<'SQL'
            CREATE TABLE pim_restaurant_periode_fermeture (
                id BINARY(16) NOT NULL,
                restaurant_id BINARY(16) NOT NULL,
                nom VARCHAR(255) NOT NULL,
                date_debut DATE DEFAULT NULL,
                date_fin DATE DEFAULT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                INDEX IDX_RESTAURANT_CLOSURE_OWNER (restaurant_id),
                INDEX IDX_RESTAURANT_CLOSURE_ORDERED (restaurant_id, date_debut, id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4
              COLLATE `utf8mb4_unicode_ci`
              ENGINE = InnoDB
            SQL,
        );
        $this->addSql(
            'ALTER TABLE pim_restaurant_periode_fermeture ADD CONSTRAINT FK_RESTAURANT_CLOSURE_OWNER FOREIGN KEY (restaurant_id) REFERENCES pim_restaurant (id) ON DELETE CASCADE',
        );

        $this->addSql(
            <<<'SQL'
            CREATE TABLE pim_restaurant_acces (
                id BINARY(16) NOT NULL,
                restaurant_id BINARY(16) NOT NULL,
                type VARCHAR(24) NOT NULL,
                nom VARCHAR(255) NOT NULL,
                position INT DEFAULT 0 NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                INDEX IDX_RESTAURANT_ACCESS_OWNER (restaurant_id),
                INDEX IDX_RESTAURANT_ACCES_ORDERED (restaurant_id, type, position, id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4
              COLLATE `utf8mb4_unicode_ci`
              ENGINE = InnoDB
            SQL,
        );
        $this->addSql(
            'ALTER TABLE pim_restaurant_acces ADD CONSTRAINT FK_RESTAURANT_ACCESS_OWNER FOREIGN KEY (restaurant_id) REFERENCES pim_restaurant (id) ON DELETE CASCADE',
        );

        $this->addSql(
            <<<'SQL'
            CREATE TABLE pim_restaurant_salle (
                id BINARY(16) NOT NULL,
                restaurant_id BINARY(16) NOT NULL,
                nom VARCHAR(255) NOT NULL,
                superficie INT DEFAULT NULL,
                capacite_reunion INT DEFAULT NULL,
                capacite_u INT DEFAULT NULL,
                capacite_classe INT DEFAULT NULL,
                capacite_theatre INT DEFAULT NULL,
                capacite_cabaret INT DEFAULT NULL,
                capacite_banquet INT DEFAULT NULL,
                capacite_cocktail INT DEFAULT NULL,
                capacite_auditorium INT DEFAULT NULL,
                lumiere_jour TINYINT(1) DEFAULT 0 NOT NULL,
                acces_pmr TINYINT(1) DEFAULT 0 NOT NULL,
                espace_dansant TINYINT(1) DEFAULT 0 NOT NULL,
                climatisee TINYINT(1) DEFAULT 0 NOT NULL,
                position INT DEFAULT 0 NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                INDEX IDX_RESTAURANT_ROOM_OWNER (restaurant_id),
                INDEX IDX_RESTAURANT_SALLE_ORDERED (restaurant_id, position, id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4
              COLLATE `utf8mb4_unicode_ci`
              ENGINE = InnoDB
            SQL,
        );
        $this->addSql(
            'ALTER TABLE pim_restaurant_salle ADD CONSTRAINT FK_RESTAURANT_ROOM_OWNER FOREIGN KEY (restaurant_id) REFERENCES pim_restaurant (id) ON DELETE CASCADE',
        );

        $this->addSql(
            'ALTER TABLE pim_ressource_lieu ADD restaurant_salle_id BINARY(16) DEFAULT NULL',
        );
        $this->addSql(
            'ALTER TABLE pim_ressource_lieu ADD CONSTRAINT FK_RESOURCE_RESTAURANT_ROOM FOREIGN KEY (restaurant_salle_id) REFERENCES pim_restaurant_salle (id) ON DELETE CASCADE',
        );
        $this->addSql(
            'CREATE INDEX IDX_RESOURCE_RESTAURANT_ROOM ON pim_ressource_lieu (restaurant_salle_id, position, id)',
        );

        foreach (RestaurantLovCatalog::attributes() as $attribute) {
            $this->addSql(
                'INSERT IGNORE INTO pim_attribute_definition (id, code, label, completeness_weight, translatable, filterable, master_application) VALUES (?, ?, ?, 0, 1, 1, \'pim\')',
                [
                    RestaurantLovCatalog::attributeId($attribute),
                    $attribute,
                    $this->attributeLabel($attribute),
                ],
            );

            $position = 0;
            foreach (RestaurantLovCatalog::values($attribute) as $code => $label) {
                $this->addSql(
                    'INSERT IGNORE INTO pim_attribute_value (id, attribute_id, code, label, position) VALUES (?, ?, ?, ?, ?)',
                    [
                        RestaurantLovCatalog::valueId($attribute, $code),
                        RestaurantLovCatalog::attributeId($attribute),
                        $code,
                        $label,
                        $position++,
                    ],
                );
            }
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM pim_fiche WHERE type = 'restaurant'");
        $this->addSql('ALTER TABLE pim_ressource_lieu DROP FOREIGN KEY FK_RESOURCE_RESTAURANT_ROOM');
        $this->addSql('DROP INDEX IDX_RESOURCE_RESTAURANT_ROOM ON pim_ressource_lieu');
        $this->addSql('ALTER TABLE pim_ressource_lieu DROP restaurant_salle_id');
        $this->addSql('DROP TABLE pim_restaurant_salle');
        $this->addSql('DROP TABLE pim_restaurant_acces');
        $this->addSql('DROP TABLE pim_restaurant_periode_fermeture');
        $this->addSql('DROP TABLE pim_restaurant');

        foreach (RestaurantLovCatalog::attributes() as $attribute) {
            $this->addSql(
                'DELETE FROM pim_fiche_attribute_value WHERE attribute_code = ?',
                [$attribute],
            );
            $this->addSql(
                'DELETE FROM pim_attribute_value WHERE attribute_id = ?',
                [RestaurantLovCatalog::attributeId($attribute)],
            );
            $this->addSql(
                'DELETE FROM pim_attribute_definition WHERE id = ?',
                [RestaurantLovCatalog::attributeId($attribute)],
            );
        }
    }

    private function attributeLabel(string $attribute): string
    {
        return match ($attribute) {
            'TYPE_RESTAURANT' => 'Types de restaurant',
            'TYPE_CUISINE' => 'Types de cuisine',
            'SPECIFICITE_ALIMENTAIRE' => 'Spécificités alimentaires',
            'TYPE_EVENEMENT' => "Types d'événements",
            'DISPO_JOUR_OUVERTURE' => "Jours d'ouverture",
            'SERVICE_RESTAURANT' => 'Services du restaurant',
            'EQUIPEMENT_RESTAURANT' => 'Équipements du restaurant',
            'ENGAGEMENT_RSE_RESTAURANT' => 'Engagements RSE du restaurant',
            default => $attribute,
        };
    }
}
