<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260810100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le téléphone des collaborateurs de fiche.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pim_fiche_collaborateur ADD phone VARCHAR(32) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pim_fiche_collaborateur DROP phone');
    }
}
