<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * L'import par modèle (écran /admin/import-fiches) a disparu : l'import en
 * masse du fichier d'export est le seul flux, le mode « écrasement » devient
 * le comportement unique et la colonne mode n'a plus lieu d'être.
 */
final class Version20260828120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Suppression de la colonne mode sur etl_import_job (écrasement seul).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE etl_import_job DROP mode');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE etl_import_job ADD mode VARCHAR(16) DEFAULT 'complement' NOT NULL AFTER failure_message");
    }
}
