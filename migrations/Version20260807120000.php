<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260807120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crée pim_fiche_relance (journal des relances de complétude envoyées aux collaborateurs).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE pim_fiche_relance (id BINARY(16) NOT NULL, fiche_id BINARY(16) NOT NULL, sent_at DATETIME NOT NULL, completeness_at_send SMALLINT UNSIGNED NOT NULL, recipient_emails JSON NOT NULL, INDEX IDX_FICHE_RELANCE_FICHE_SENT (fiche_id, sent_at), PRIMARY KEY(id), CONSTRAINT FK_FICHE_RELANCE_FICHE FOREIGN KEY (fiche_id) REFERENCES pim_fiche (id) ON DELETE CASCADE) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE pim_fiche_relance');
    }
}
