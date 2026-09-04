<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Service événementiel, onglet Localisation & accessibilité (maquette
 * portail) : collection d'accès (route, parking, gare, aéroport) et deux
 * réponses PMR.
 */
final class Version20260904110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Service : table pim_service_acces + accès PMR / matériel adapté PMR.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE pim_service_acces (
                id BINARY(16) NOT NULL,
                service_id BINARY(16) NOT NULL,
                type VARCHAR(24) NOT NULL,
                nom VARCHAR(255) NOT NULL,
                position INT DEFAULT 0 NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                INDEX IDX_SERVICE_ACCES_OWNER (service_id),
                INDEX IDX_SERVICE_ACCES_ORDERED (service_id, type, position, id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);
        $this->addSql('ALTER TABLE pim_service_acces ADD CONSTRAINT FK_SERVICE_ACCES_OWNER FOREIGN KEY (service_id) REFERENCES pim_service_evenementiel (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE pim_service_evenementiel ADD acces_pmr TINYINT(1) DEFAULT NULL, ADD materiel_adapte_pmr TINYINT(1) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pim_service_evenementiel DROP acces_pmr, DROP materiel_adapte_pmr');
        $this->addSql('DROP TABLE pim_service_acces');
    }
}
