<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260727020000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les comptes locaux utilisés pour authentifier et autoriser les utilisateurs du MDM.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "CREATE TABLE account_user (
                id VARCHAR(26) NOT NULL,
                email VARCHAR(180) NOT NULL,
                password VARCHAR(255) NOT NULL,
                roles JSON NOT NULL,
                is_active TINYINT(1) DEFAULT 1 NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE INDEX UNIQ_ACCOUNT_USER_EMAIL (email),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB",
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE account_user');
    }
}
