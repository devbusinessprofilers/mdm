<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260810150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le drapeau Adhérent Business Premium sur les fiches.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pim_fiche ADD business_premium TINYINT(1) DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pim_fiche DROP business_premium');
    }
}
