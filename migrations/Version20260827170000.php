<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Exports Excel du référentiel : une ligne par demande (code unique ULID,
 * page de suivi partageable, génération par le worker, journal /outils).
 */
final class Version20260827170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Table pim_referentiel_export (exports Excel du référentiel, suivi et historique).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE pim_referentiel_export ("
            ."id BINARY(16) NOT NULL COMMENT '(DC2Type:ulid)', "
            ."demandeur VARCHAR(180) NOT NULL, "
            ."statut VARCHAR(16) NOT NULL, "
            ."colonnes JSON NOT NULL, "
            ."ids JSON DEFAULT NULL, "
            ."filtres JSON NOT NULL, "
            ."nb INT NOT NULL, "
            ."requested_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', "
            ."finished_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', "
            ."erreur LONGTEXT DEFAULT NULL, "
            ."INDEX IDX_REFERENTIEL_EXPORT_REQUESTED (requested_at), "
            ."PRIMARY KEY (id)"
            .") DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE pim_referentiel_export');
    }
}
