<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260805110304 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create dashboard_snapshot table storing precomputed dashboard statistics history';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE dashboard_snapshot (id BINARY(16) NOT NULL, schema_version SMALLINT UNSIGNED NOT NULL, payload JSON NOT NULL, computed_at DATETIME NOT NULL, duration_ms INT UNSIGNED NOT NULL, INDEX IDX_DASHBOARD_SNAPSHOT_COMPUTED (computed_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE dashboard_snapshot');
    }
}
