<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260810180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le drapeau de changement de mot de passe obligatoire.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE account_user ADD must_change_password TINYINT(1) DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE account_user DROP must_change_password');
    }
}
