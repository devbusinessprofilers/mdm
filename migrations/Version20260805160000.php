<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260805160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute un index (updated_at, id) sur pim_fiche pour la liste globale des fiches.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX IDX_PIM_FICHE_UPDATED ON pim_fiche (updated_at, id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_PIM_FICHE_UPDATED ON pim_fiche');
    }
}
