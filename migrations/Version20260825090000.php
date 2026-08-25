<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260825090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Backoff des envois Salesforce Produits : compteur d\'échecs et prochain essai autorisé.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE etl_fiche_salesforce_export ADD failure_count INT UNSIGNED DEFAULT 0 NOT NULL, ADD retry_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE etl_fiche_salesforce_export DROP failure_count, DROP retry_at');
    }
}
