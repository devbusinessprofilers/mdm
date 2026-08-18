<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Horaires d'ouverture par jour sur la fiche Lieu (éditeur maquette) :
 * colonne JSON {jour: {ouverture: 'HH:MM', fermeture: 'HH:MM'}}. Les quatre
 * champs globaux historiques restent le contrat marketplace, dérivés de
 * l'amplitude à la saisie.
 */
final class Version20260818120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les horaires par jour (JSON) à la fiche Lieu.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pim_lieu ADD dispo_horaires_jours JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pim_lieu DROP dispo_horaires_jours');
    }
}
