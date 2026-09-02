<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Visibilité géographique automatique (CDC §10.1) : un site de diffusion
 * porte une liste de critères géographiques (ville + rayon, département,
 * région, pays) sérialisés en JSON. Ajout en trois temps : MySQL refuse un
 * NOT NULL sans défaut sur une table peuplée et un JSON ne porte pas de
 * défaut littéral.
 */
final class Version20260902090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add criteres_geo JSON column to pim_site_diffusion (automatic geographic visibility).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pim_site_diffusion ADD criteres_geo JSON DEFAULT NULL');
        $this->addSql("UPDATE pim_site_diffusion SET criteres_geo = '[]'");
        $this->addSql('ALTER TABLE pim_site_diffusion MODIFY criteres_geo JSON NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pim_site_diffusion DROP criteres_geo');
    }
}
