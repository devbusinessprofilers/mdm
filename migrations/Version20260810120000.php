<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260810120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crée le référentiel des sites de diffusion et la sélection par fiche.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE pim_site_diffusion ('
            .'id INT UNSIGNED AUTO_INCREMENT NOT NULL, '
            .'code VARCHAR(64) NOT NULL, '
            .'label VARCHAR(255) NOT NULL, '
            .'groupe VARCHAR(128) NOT NULL, '
            .'obligatoire TINYINT(1) DEFAULT 0 NOT NULL, '
            .'payant TINYINT(1) DEFAULT 0 NOT NULL, '
            .'actif TINYINT(1) DEFAULT 1 NOT NULL, '
            .'position SMALLINT UNSIGNED DEFAULT 0 NOT NULL, '
            .'gammes_par_defaut JSON NOT NULL, '
            .'UNIQUE INDEX UNIQ_PIM_SITE_DIFFUSION_CODE (code), '
            .'INDEX IDX_PIM_SITE_DIFFUSION_ACTIF (actif, position), '
            .'PRIMARY KEY (id)'
            .') DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`',
        );
        $this->addSql(
            'CREATE TABLE pim_fiche_site_diffusion ('
            .'fiche_id BINARY(16) NOT NULL, '
            .'site_id INT UNSIGNED NOT NULL, '
            .'INDEX IDX_PIM_FSD_SITE_FICHE (site_id, fiche_id), '
            .'PRIMARY KEY (fiche_id, site_id)'
            .') DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`',
        );
        $this->addSql(
            'ALTER TABLE pim_fiche_site_diffusion '
            .'ADD CONSTRAINT FK_PIM_FSD_FICHE FOREIGN KEY (fiche_id) REFERENCES pim_fiche (id) ON DELETE CASCADE, '
            .'ADD CONSTRAINT FK_PIM_FSD_SITE FOREIGN KEY (site_id) REFERENCES pim_site_diffusion (id) ON DELETE CASCADE',
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE pim_fiche_site_diffusion');
        $this->addSql('DROP TABLE pim_site_diffusion');
    }
}
