<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Traçabilité de la vérification des adresses françaises contre l'API
 * Adresse (BAN, data.gouv) : score de confiance et date du dernier passage
 * de app:localisation:verifier — évite de revérifier tout le stock et
 * permettra un filtre « adresses douteuses ».
 */
final class Version20260813230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la trace de vérification BAN (score, date) sur pim_localisation.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE pim_localisation'
            .' ADD ban_score DOUBLE PRECISION DEFAULT NULL,'
            ." ADD ban_verifie_le DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'",
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE pim_localisation'
            .' DROP COLUMN ban_score,'
            .' DROP COLUMN ban_verifie_le',
        );
    }
}
