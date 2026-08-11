<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260811120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crée la table sessions pour le stockage des sessions PHP en base (PdoSessionHandler).';
    }

    public function up(Schema $schema): void
    {
        // Schéma attendu par PdoSessionHandler : identifiants et données binaires,
        // index sur sess_lifetime pour le garbage collector.
        $this->addSql(
            'CREATE TABLE sessions ('
            .'sess_id VARBINARY(128) NOT NULL, '
            .'sess_data BLOB NOT NULL, '
            .'sess_lifetime INT UNSIGNED NOT NULL, '
            .'sess_time INT UNSIGNED NOT NULL, '
            .'INDEX IDX_SESSIONS_LIFETIME (sess_lifetime), '
            .'PRIMARY KEY (sess_id)'
            .') COLLATE `utf8mb4_bin`, ENGINE = InnoDB',
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE sessions');
    }
}
