<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * La retouche gagne un second moteur, local et gratuit (ImageMagick), à côté
 * du moteur génératif OpenAI. Chaque retouche porte son fournisseur ; les
 * lignes existantes sont toutes issues d'OpenAI.
 */
final class Version20260901100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Retouche : colonne provider (openai/imagemagick) et index de listing par fournisseur.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE vision_image_enhancement ADD provider VARCHAR(32) DEFAULT 'openai' NOT NULL");
        $this->addSql('CREATE INDEX IDX_VISION_ENHANCEMENT_PROVIDER_CREATED ON vision_image_enhancement (provider, created_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_VISION_ENHANCEMENT_PROVIDER_CREATED ON vision_image_enhancement');
        $this->addSql('ALTER TABLE vision_image_enhancement DROP provider');
    }
}
