<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Fusion de fiches : la fiche absorbée reste techniquement Archivée (tous les
 * flux existants — retrait marketplace, filtres, exports — la traitent déjà
 * correctement) mais porte l'identifiant de la fiche survivante. L'interface
 * dérive le libellé « Fusionnée » de cette colonne.
 */
final class Version20260831120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute pim_fiche.merged_into_id : trace de la fiche survivante après une fusion.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pim_fiche ADD merged_into_id BINARY(16) DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_PIM_FICHE_MERGED_INTO ON pim_fiche (merged_into_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_PIM_FICHE_MERGED_INTO ON pim_fiche');
        $this->addSql('ALTER TABLE pim_fiche DROP merged_into_id');
    }
}
