<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Référentiels géographiques statiques des suggestions du bloc Accès :
 * aéroports mondiaux à trafic régulier (OurAirports) et villes de 15 000
 * habitants et plus (GeoNames cities15000). Tables remplies par
 * app:acces:importer-aeroports et app:acces:importer-grandes-villes.
 */
final class Version20260901130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Référentiels aéroports (OurAirports) et grandes villes (GeoNames) pour les suggestions du bloc Accès.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE pim_aeroport_reference (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, code_iata VARCHAR(3) DEFAULT NULL, code_pays VARCHAR(2) NOT NULL, latitude DOUBLE PRECISION NOT NULL, longitude DOUBLE PRECISION NOT NULL, INDEX IDX_PIM_AEROPORT_REF_GEO (latitude, longitude), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE pim_grande_ville_reference (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, code_pays VARCHAR(2) NOT NULL, population INT NOT NULL, latitude DOUBLE PRECISION NOT NULL, longitude DOUBLE PRECISION NOT NULL, INDEX IDX_PIM_GRANDE_VILLE_REF_GEO (latitude, longitude), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE pim_aeroport_reference');
        $this->addSql('DROP TABLE pim_grande_ville_reference');
    }
}
