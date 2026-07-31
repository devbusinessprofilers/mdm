<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260730200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute un index sur pim_ressource_lieu.dam_asset_id pour la résolution ressource par média.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE INDEX IDX_PIM_RESSOURCE_DAM_ASSET ON pim_ressource_lieu (dam_asset_id)',
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            'DROP INDEX IDX_PIM_RESSOURCE_DAM_ASSET ON pim_ressource_lieu',
        );
    }
}
