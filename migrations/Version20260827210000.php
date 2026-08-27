<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Import en masse (Outils) : le fichier d'export du référentiel se réimporte
 * en mode « écrasement » (le fichier fait foi, libellés LOV acceptés), à côté
 * du mode historique « complément » de l'import par modèle.
 */
final class Version20260827210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Colonne mode sur etl_import_job (complement | ecrasement).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE etl_import_job ADD mode VARCHAR(16) DEFAULT 'complement' NOT NULL AFTER failure_message");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE etl_import_job DROP mode');
    }
}
