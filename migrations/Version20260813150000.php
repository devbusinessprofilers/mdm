<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Téléphone public de l'établissement sur la fiche : la marketplace affiche
 * bp_produit.telephone, alimenté jusqu'ici par le CSV legacy — le PIM
 * devient la source (colonne CSV « Téléphone » reprise à l'import).
 */
final class Version20260813150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le téléphone public sur la fiche.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pim_fiche ADD telephone VARCHAR(20) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pim_fiche DROP COLUMN telephone');
    }
}
