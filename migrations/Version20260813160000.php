<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Partenaire BP sur la fiche : source de l'icône partenaire de la
 * marketplace (bp_produit.is_partenaire), jusqu'ici déduite de la colonne
 * « Tag » du CSV legacy (vide = partenaire). Distinct de businessPremium
 * qui pilote les relances de complétude.
 */
final class Version20260813160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le drapeau Partenaire BP sur la fiche.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE pim_fiche ADD partenaire_bp TINYINT(1) DEFAULT 0 NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pim_fiche DROP COLUMN partenaire_bp');
    }
}
