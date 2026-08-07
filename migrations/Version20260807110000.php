<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260807110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le type de snapshot dashboard (stats / field_fill) pour héberger le calcul des champs peu renseignés.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE dashboard_snapshot ADD kind VARCHAR(32) DEFAULT 'stats' NOT NULL");
        $this->addSql('CREATE INDEX IDX_DASHBOARD_SNAPSHOT_KIND ON dashboard_snapshot (kind, computed_at, id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_DASHBOARD_SNAPSHOT_KIND ON dashboard_snapshot');
        $this->addSql('ALTER TABLE dashboard_snapshot DROP kind');
    }
}
