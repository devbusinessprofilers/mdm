<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260811100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crée le suivi de diffusion marketplace et le site de diffusion marketplace_bp.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE etl_fiche_marketplace ('
            ."fiche_id BINARY(16) NOT NULL COMMENT '(DC2Type:ulid)', "
            .'code INT UNSIGNED NOT NULL, '
            .'status VARCHAR(16) NOT NULL, '
            .'last_sequence VARCHAR(26) DEFAULT NULL, '
            ."synced_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', "
            .'last_error LONGTEXT DEFAULT NULL, '
            ."updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', "
            .'INDEX IDX_ETL_MARKETPLACE_STATUS (status, updated_at), '
            .'PRIMARY KEY (fiche_id)'
            .') DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`',
        );
        // Sans FK vers pim_fiche : le suivi doit survivre à une suppression de
        // fiche pour permettre la dépublication a posteriori.
        $this->addSql(
            "INSERT INTO pim_site_diffusion (code, label, groupe, obligatoire, payant, actif, position, gammes_par_defaut) "
            ."SELECT 'marketplace_bp', 'Marketplace Business Profilers', 'Business Profilers', 0, 0, 1, 0, JSON_ARRAY() "
            ."WHERE NOT EXISTS (SELECT 1 FROM pim_site_diffusion WHERE code = 'marketplace_bp')",
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE etl_fiche_marketplace');
        $this->addSql("DELETE FROM pim_site_diffusion WHERE code = 'marketplace_bp'");
    }
}
