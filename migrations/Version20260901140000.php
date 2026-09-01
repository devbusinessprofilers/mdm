<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Référentiel statique du classement officiel des hébergements touristiques
 * (open data Atout France) : source des suggestions d'étoiles (typologie) et
 * de nombre de chambres. Rempli par app:pim:importer-classements-atout-france.
 */
final class Version20260901140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Référentiel des classements Atout France pour les suggestions d\'étoiles du bloc enrichissement.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE pim_classement_atout_france (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, code_postal VARCHAR(8) NOT NULL, commune VARCHAR(255) NOT NULL, type_etablissement VARCHAR(64) NOT NULL, etoiles INT NOT NULL, nombre_chambres INT DEFAULT NULL, date_classement DATE DEFAULT NULL, INDEX IDX_PIM_CLASSEMENT_AF_CP (code_postal), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE pim_classement_atout_france');
    }
}
