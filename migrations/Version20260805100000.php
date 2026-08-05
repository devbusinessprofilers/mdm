<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260805100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le reset de mot de passe, le renouvellement des invitations et l’anonymisation des utilisateurs internes.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE account_user ADD deleted_at DATETIME DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_ACCOUNT_USER_DELETED ON account_user (deleted_at)');
        $this->addSql('ALTER TABLE account_invitation ADD invalidated_at DATETIME DEFAULT NULL');
        $this->addSql(<<<'SQL'
            CREATE TABLE account_password_reset_request (
                id VARCHAR(26) NOT NULL,
                user_id VARCHAR(26) NOT NULL,
                expires_at DATETIME NOT NULL,
                used_at DATETIME DEFAULT NULL,
                invalidated_at DATETIME DEFAULT NULL,
                created_at DATETIME NOT NULL,
                INDEX IDX_ACCOUNT_PASSWORD_RESET_USER (user_id),
                PRIMARY KEY(id),
                CONSTRAINT FK_ACCOUNT_PASSWORD_RESET_USER FOREIGN KEY (user_id) REFERENCES account_user (id) ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE account_password_reset_request');
        $this->addSql('ALTER TABLE account_invitation DROP invalidated_at');
        $this->addSql('DROP INDEX IDX_ACCOUNT_USER_DELETED ON account_user');
        $this->addSql('ALTER TABLE account_user DROP deleted_at');
    }
}
