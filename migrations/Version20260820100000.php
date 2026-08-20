<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260820100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Droits maquette des affiliations : traite les contenus, traite les paiements.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pim_fiche_affiliation ADD traite_contenus TINYINT(1) DEFAULT 0 NOT NULL, ADD traite_paiements TINYINT(1) DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pim_fiche_affiliation DROP traite_contenus, DROP traite_paiements');
    }
}
