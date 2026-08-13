<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Offre spéciale et dates de promotion sur le bloc « Tarifs & formules »
 * de la fiche Lieu : reprise du bloc promo de la marketplace
 * (bp_lieu.offre_speciale_fr + promotion_start/end_date), alimenté
 * jusqu'ici par le CSV legacy.
 */
final class Version20260813170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute l\'offre spéciale et les dates de promotion à la tarification des lieux.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE pim_lieu_tarification'
            .' ADD offre_speciale LONGTEXT DEFAULT NULL,'
            .' ADD promotion_debut DATE DEFAULT NULL,'
            .' ADD promotion_fin DATE DEFAULT NULL',
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE pim_lieu_tarification'
            .' DROP COLUMN offre_speciale,'
            .' DROP COLUMN promotion_debut,'
            .' DROP COLUMN promotion_fin',
        );
    }
}
