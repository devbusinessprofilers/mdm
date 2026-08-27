<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Index FULLTEXT sur le nom de fiche, pour l'autocomplétion de la recherche :
 * les LIKE plein-scan des suggestions basculent sur MATCH AGAINST. Posé
 * pendant que la table est petite — le premier index FULLTEXT d'une table
 * InnoDB force sa reconstruction (colonne cachée FTS_DOC_ID), coût qui croît
 * avec le volume.
 */
final class Version20260827090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Index FULLTEXT sur pim_fiche.label (autocomplétion de la recherche).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE FULLTEXT INDEX FTX_PIM_FICHE_LABEL ON pim_fiche (label)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX FTX_PIM_FICHE_LABEL ON pim_fiche');
    }
}
