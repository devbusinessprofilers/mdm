<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Onglet Tarifs du Restaurant (maquette portail prestataire) : six montants
 * HT « à partir de », null = prestation non proposée.
 */
final class Version20260904100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Restaurant : six tarifs « à partir de » (déjeuner/dîner assis, cocktails, forfaits vin/alcool).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pim_restaurant ADD tarif_dejeuner_assis NUMERIC(12, 2) DEFAULT NULL, ADD tarif_cocktail_dejeunatoire NUMERIC(12, 2) DEFAULT NULL, ADD tarif_diner_assis NUMERIC(12, 2) DEFAULT NULL, ADD tarif_cocktail_dinatoire NUMERIC(12, 2) DEFAULT NULL, ADD tarif_forfait_vin NUMERIC(12, 2) DEFAULT NULL, ADD tarif_forfait_alcool NUMERIC(12, 2) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pim_restaurant DROP tarif_dejeuner_assis, DROP tarif_cocktail_dejeunatoire, DROP tarif_diner_assis, DROP tarif_cocktail_dinatoire, DROP tarif_forfait_vin, DROP tarif_forfait_alcool');
    }
}
