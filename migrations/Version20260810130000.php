<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260810130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crée les vues enregistrées de la liste du référentiel.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE pim_saved_view ('
            .'id INT UNSIGNED AUTO_INCREMENT NOT NULL, '
            .'name VARCHAR(120) NOT NULL, '
            .'owner_id VARCHAR(26) NOT NULL, '
            .'owner_label VARCHAR(180) NOT NULL, '
            .'shared TINYINT(1) DEFAULT 0 NOT NULL, '
            .'filters JSON NOT NULL, '
            .'created_at DATETIME NOT NULL, '
            .'updated_at DATETIME NOT NULL, '
            .'INDEX IDX_PIM_SAVED_VIEW_OWNER (owner_id, shared), '
            .'PRIMARY KEY (id)'
            .') DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`',
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE pim_saved_view');
    }
}
