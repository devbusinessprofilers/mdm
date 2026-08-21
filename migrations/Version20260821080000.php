<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260821080000 extends AbstractMigration
{
    public function getDescription(): string { return 'Indexe dam_media_asset par (kind, status, created_at) pour la bibliothèque des médias.'; }

    public function up(Schema $schema): void
    {
        // La bibliothèque liste et compte par kind + status (tri created_at) :
        // l'index existant (status, created_at) ne couvre pas le filtre kind.
        $this->addSql('CREATE INDEX IDX_DAM_MEDIA_KIND_STATUS_CREATED ON dam_media_asset (kind, status, created_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_DAM_MEDIA_KIND_STATUS_CREATED ON dam_media_asset');
    }
}
