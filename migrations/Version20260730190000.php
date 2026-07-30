<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260730190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la validation des fiches Lieu, renomme ROLE_BP et retire l identite Provider.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE account_user SET roles = REPLACE(roles, '\"ROLE_BP\"', '\"ROLE_BP_VALIDATOR\"')");
        $this->addSql("UPDATE pim_fiche SET status = 'publiee' WHERE status = 'validee'");
        if (!$schema->getTable('pim_fiche')->hasColumn('validation_requested_at')) {
            $this->addSql('ALTER TABLE pim_fiche MODIFY status VARCHAR(32) NOT NULL, ADD validation_requested_at DATETIME DEFAULT NULL, ADD validation_requested_by VARCHAR(26) DEFAULT NULL, ADD validation_reviewed_at DATETIME DEFAULT NULL, ADD validation_reviewed_by VARCHAR(26) DEFAULT NULL, ADD validation_feedback LONGTEXT DEFAULT NULL');
        }
        $this->addSql('ALTER TABLE account_user MODIFY password VARCHAR(255) DEFAULT NULL');
        if (!$schema->hasTable('account_invitation')) {
            $this->addSql("CREATE TABLE account_invitation (id VARCHAR(26) NOT NULL, user_id VARCHAR(26) NOT NULL, expires_at DATETIME NOT NULL, accepted_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, INDEX IDX_ACCOUNT_INVITATION_USER (user_id), PRIMARY KEY(id), CONSTRAINT FK_ACCOUNT_INVITATION_USER FOREIGN KEY (user_id) REFERENCES account_user (id) ON DELETE CASCADE) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE account_user SET roles = REPLACE(roles, '\"ROLE_BP_VALIDATOR\"', '\"ROLE_BP\"')");
        $this->addSql("UPDATE pim_fiche SET status = 'en_cours' WHERE status = 'en_attente_validation'");
        $this->addSql('ALTER TABLE pim_fiche MODIFY status VARCHAR(16) NOT NULL, DROP validation_requested_at, DROP validation_requested_by, DROP validation_reviewed_at, DROP validation_reviewed_by, DROP validation_feedback');
        $this->addSql('DROP TABLE account_invitation');
        $this->addSql("UPDATE account_user SET password = '' WHERE password IS NULL");
        $this->addSql('ALTER TABLE account_user MODIFY password VARCHAR(255) NOT NULL');
    }
}
