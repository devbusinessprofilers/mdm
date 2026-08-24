<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260824160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Ajoute pim_lieu.chaine_hoteliere : affiliation à un groupe/chaîne (enrichissement Wikidata).";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pim_lieu ADD chaine_hoteliere VARCHAR(120) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pim_lieu DROP chaine_hoteliere');
    }
}
