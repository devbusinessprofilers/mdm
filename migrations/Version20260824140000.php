<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260824140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Ajoute pim_fiche_suggestion.payload : données machine d'application (codes LOV à fusionner, booléen).";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pim_fiche_suggestion ADD payload JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pim_fiche_suggestion DROP payload');
    }
}
