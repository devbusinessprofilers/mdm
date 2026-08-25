<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Liaison 1–1 Lieu ↔ Restaurant : un lieu peut posséder un restaurant
 * (colonne lieu_id unique sur pim_restaurant, côté propriétaire). La
 * suppression du lieu détache le restaurant (SET NULL), la fiche survit.
 */
final class Version20260825200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Liaison 1–1 Lieu ↔ Restaurant (pim_restaurant.lieu_id).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pim_restaurant ADD lieu_id BINARY(16) DEFAULT NULL');
        $this->addSql('ALTER TABLE pim_restaurant ADD CONSTRAINT FK_E71624796AB213CC FOREIGN KEY (lieu_id) REFERENCES pim_lieu (id) ON DELETE SET NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_E71624796AB213CC ON pim_restaurant (lieu_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pim_restaurant DROP FOREIGN KEY FK_E71624796AB213CC');
        $this->addSql('DROP INDEX UNIQ_E71624796AB213CC ON pim_restaurant');
        $this->addSql('ALTER TABLE pim_restaurant DROP COLUMN lieu_id');
    }
}
