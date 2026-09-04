<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Accès des Restaurants et des Services alignés sur ceux du Lieu :
 * distance, durée et mode de transport (relecture maquette 2026-09-04).
 */
final class Version20260904130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Accès Restaurant / Service : distance, durée, mode de transport comme le Lieu.';
    }

    public function up(Schema $schema): void
    {
        foreach (['pim_restaurant_acces', 'pim_service_acces'] as $table) {
            $this->addSql(sprintf('ALTER TABLE %s ADD distance_kilometres NUMERIC(8, 2) DEFAULT NULL, ADD duree_minutes INT DEFAULT NULL, ADD mode_transport VARCHAR(255) DEFAULT NULL', $table));
        }
    }

    public function down(Schema $schema): void
    {
        foreach (['pim_restaurant_acces', 'pim_service_acces'] as $table) {
            $this->addSql(sprintf('ALTER TABLE %s DROP distance_kilometres, DROP duree_minutes, DROP mode_transport', $table));
        }
    }
}
