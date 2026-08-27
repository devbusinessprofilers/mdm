<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Les classeurs d'export vivent sur le bucket privé OVH avec une rétention
 * de 30 jours : la date d'expiration s'affiche dans l'historique et pilote
 * la purge quotidienne.
 */
final class Version20260827200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Colonne expires_at sur pim_referentiel_export (rétention 30 jours du classeur).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE pim_referentiel_export ADD expires_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)' AFTER finished_at");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pim_referentiel_export DROP expires_at');
    }
}
