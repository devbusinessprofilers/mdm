<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Reprise du thème legacy « RSE » du lieu en case à cocher dédiée. Le thème
 * legacy « Esat » est repris via la typologie existante « Lieu ESAT ».
 */
final class Version20260814120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la case demarche_rse au lieu.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pim_lieu ADD demarche_rse TINYINT(1) DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pim_lieu DROP demarche_rse');
    }
}
