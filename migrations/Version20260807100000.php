<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260807100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crée dam_anomaly (incohérences détectées par le scan DAM) et indexe (fiche_id, nature) sur les ressources.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE dam_anomaly (id BINARY(16) NOT NULL, type VARCHAR(32) NOT NULL, subject_id VARCHAR(255) NOT NULL, detected_at DATETIME NOT NULL, resolved_at DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_DAM_ANOMALY_SUBJECT (type, subject_id), INDEX IDX_DAM_ANOMALY_OPEN (type, resolved_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('CREATE INDEX IDX_PIM_RESOURCE_FICHE_NATURE ON pim_ressource_lieu (fiche_id, nature)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_PIM_RESOURCE_FICHE_NATURE ON pim_ressource_lieu');
        $this->addSql('DROP TABLE dam_anomaly');
    }
}
