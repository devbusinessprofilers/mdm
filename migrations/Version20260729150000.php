<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260729150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les colonnes temporaires pour convertir les ULID texte en BINARY(16) sans perte.';
    }

    public function up(Schema $schema): void
    {
        foreach (['pim_lieu', 'pim_localisation', 'pim_salle', 'pim_periode_fermeture', 'pim_acces_lieu', 'pim_ressource_lieu'] as $table) {
            $this->addSql(sprintf('ALTER TABLE %s ADD id_binary BINARY(16) DEFAULT NULL', $table));
        }
        foreach (['pim_salle', 'pim_periode_fermeture', 'pim_acces_lieu', 'pim_ressource_lieu'] as $table) {
            $this->addSql(sprintf('ALTER TABLE %s ADD lieu_id_binary BINARY(16) DEFAULT NULL', $table));
        }
        $this->addSql('ALTER TABLE pim_ressource_lieu ADD salle_id_binary BINARY(16) DEFAULT NULL');
        $this->addSql('ALTER TABLE pim_lieu ADD localisation_id_binary BINARY(16) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pim_lieu DROP id_binary, DROP localisation_id_binary');
        $this->addSql('ALTER TABLE pim_localisation DROP id_binary');
        $this->addSql('ALTER TABLE pim_salle DROP id_binary, DROP lieu_id_binary');
        $this->addSql('ALTER TABLE pim_periode_fermeture DROP id_binary, DROP lieu_id_binary');
        $this->addSql('ALTER TABLE pim_acces_lieu DROP id_binary, DROP lieu_id_binary');
        $this->addSql('ALTER TABLE pim_ressource_lieu DROP id_binary, DROP lieu_id_binary, DROP salle_id_binary');
    }
}
