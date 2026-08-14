<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Vérification BAN au fil de l'eau : empreinte d'adresse au moment du
 * passage (revérifier seulement ce qui a changé), proposition BAN
 * conservée et drapeau d'écart précalculé pour le bloc « Suggestions
 * d'adresse » de l'écran Qualité.
 */
final class Version20260813250000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Étend la trace BAN de pim_localisation (empreinte, proposition, écart à arbitrer).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE pim_localisation'
            .' ADD ban_fingerprint BINARY(32) DEFAULT NULL,'
            .' ADD ban_proposition JSON DEFAULT NULL,'
            .' ADD ban_ecart TINYINT(1) DEFAULT 0 NOT NULL',
        );
        $this->addSql('CREATE INDEX IDX_PIM_LOCALISATION_BAN_ECART ON pim_localisation (ban_ecart)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_PIM_LOCALISATION_BAN_ECART ON pim_localisation');
        $this->addSql(
            'ALTER TABLE pim_localisation'
            .' DROP COLUMN ban_fingerprint,'
            .' DROP COLUMN ban_proposition,'
            .' DROP COLUMN ban_ecart',
        );
    }
}
