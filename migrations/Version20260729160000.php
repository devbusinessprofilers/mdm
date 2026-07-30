<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260729160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les collaborateurs prestataires et leurs affiliations aux fiches, sans créer de compte PIM.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE pim_fiche_collaborateur (id BINARY(16) NOT NULL, email VARCHAR(180) NOT NULL, first_name VARCHAR(100) DEFAULT '' NOT NULL, last_name VARCHAR(100) DEFAULT '' NOT NULL, language VARCHAR(8) DEFAULT 'fr' NOT NULL, is_active TINYINT(1) DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_FICHE_COLLABORATEUR_EMAIL (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql("CREATE TABLE pim_fiche_affiliation (id BINARY(16) NOT NULL, collaborateur_id BINARY(16) NOT NULL, fiche_id BINARY(16) NOT NULL, created_by_user_id VARCHAR(26) DEFAULT NULL, created_by_collaborateur_id BINARY(16) DEFAULT NULL, role VARCHAR(24) NOT NULL, receives_requests TINYINT(1) DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_AFFILIATION_COLLABORATEUR_FICHE (collaborateur_id, fiche_id), INDEX IDX_AFFILIATION_FICHE_ROLE (fiche_id, role), INDEX IDX_AFFILIATION_FICHE_REQUESTS (fiche_id, receives_requests), INDEX IDX_806DF5827D182D95 (created_by_user_id), INDEX IDX_806DF5827EB43F91 (created_by_collaborateur_id), CONSTRAINT CHK_AFFILIATION_SINGLE_AUTHOR CHECK ((created_by_user_id IS NULL) <> (created_by_collaborateur_id IS NULL)), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE pim_fiche_affiliation ADD CONSTRAINT FK_AFFILIATION_COLLABORATEUR FOREIGN KEY (collaborateur_id) REFERENCES pim_fiche_collaborateur (id) ON DELETE CASCADE, ADD CONSTRAINT FK_AFFILIATION_FICHE FOREIGN KEY (fiche_id) REFERENCES pim_fiche (id) ON DELETE CASCADE, ADD CONSTRAINT FK_AFFILIATION_CREATED_BY_USER FOREIGN KEY (created_by_user_id) REFERENCES account_user (id) ON DELETE RESTRICT, ADD CONSTRAINT FK_AFFILIATION_CREATED_BY_COLLABORATEUR FOREIGN KEY (created_by_collaborateur_id) REFERENCES pim_fiche_collaborateur (id) ON DELETE RESTRICT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE pim_fiche_affiliation');
        $this->addSql('DROP TABLE pim_fiche_collaborateur');
    }
}
