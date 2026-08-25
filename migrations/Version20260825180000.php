<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Supprime la LOV SITE_PREMIUM : ses 37 valeurs doublonnaient le référentiel
 * des sites de diffusion (décision 2026-08-25 — la visibilité n'a qu'un seul
 * champ, la sélection de sites de diffusion, alimentée à l'import par la
 * colonne « Attribution visibilité »). Aucune fiche n'a jamais porté de
 * valeur SITE_PREMIUM en production ; la suppression de la définition
 * cascade sur pim_attribute_value et les tables de traduction.
 */
final class Version20260825180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Supprime la LOV SITE_PREMIUM (doublon du référentiel des sites de diffusion).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("DELETE FROM pim_fiche_attribute_value WHERE attribute_code = 'SITE_PREMIUM'");
        $this->addSql("DELETE FROM pim_attribute_definition WHERE code = 'SITE_PREMIUM'");
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('La LOV SITE_PREMIUM supprimée ne se reconstruit pas (référentiel des sites de diffusion fait foi).');
    }
}
