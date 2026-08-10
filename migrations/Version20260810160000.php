<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260810160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le contributeur interne assigné aux fiches.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pim_fiche ADD assignee_id VARCHAR(26) DEFAULT NULL');
        $this->addSql('ALTER TABLE pim_fiche ADD CONSTRAINT FK_4486C08959EC7D60 FOREIGN KEY (assignee_id) REFERENCES account_user (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_4486C08959EC7D60 ON pim_fiche (assignee_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pim_fiche DROP FOREIGN KEY FK_4486C08959EC7D60');
        $this->addSql('DROP INDEX IDX_4486C08959EC7D60 ON pim_fiche');
        $this->addSql('ALTER TABLE pim_fiche DROP assignee_id');
    }
}
