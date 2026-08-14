<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Reprise des thèmes legacy « Esat » et « RSE » du lieu en cases à cocher
 * dédiées (au lieu de valeurs de liste sans équivalent au dictionnaire).
 */
final class Version20260814120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les cases esat et demarche_rse au lieu.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pim_lieu ADD esat TINYINT(1) DEFAULT 0 NOT NULL, ADD demarche_rse TINYINT(1) DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pim_lieu DROP esat, DROP demarche_rse');
    }
}
