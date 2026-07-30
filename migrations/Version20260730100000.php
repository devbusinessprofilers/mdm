<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260730100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les deux champs Lieu actifs manquants dans la Bible des attributs.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pim_lieu ADD desc_generale_point_interet LONGTEXT DEFAULT NULL, ADD rse_desc_generale LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pim_lieu DROP desc_generale_point_interet, DROP rse_desc_generale');
    }
}
