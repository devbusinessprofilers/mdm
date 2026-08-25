<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Journal des demandes d'enrichissement (bouton « Enrichir ce qui manque ») :
 * une ligne par clic, visible dans /outils dès la demande (en file) puis avec
 * le résultat par source une fois traitée par le worker. Ajoute aussi les
 * index updated_at qui manquaient pour lister traductions et médias « tous
 * statuts » dans le journal (556k/167k lignes : tri sans index = filesort).
 */
final class Version20260825140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crée pim_fiche_enrichment_run (journal du bouton Enrichir) + index updated_at traductions/médias.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE pim_fiche_enrichment_run (id BINARY(16) NOT NULL COMMENT '(DC2Type:ulid)', fiche_id BINARY(16) NOT NULL COMMENT '(DC2Type:ulid)', requested_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', finished_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', resultat JSON DEFAULT NULL, INDEX IDX_FICHE_ENRICHMENT_RUN_REQUESTED (requested_at), INDEX IDX_FICHE_ENRICHMENT_RUN_FICHE (fiche_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`");
        $this->addSql('ALTER TABLE pim_fiche_enrichment_run ADD CONSTRAINT FK_FICHE_ENRICHMENT_RUN_FICHE FOREIGN KEY (fiche_id) REFERENCES pim_fiche (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_ENRICHMENT_FICHE_TRANSLATION_UPDATED ON enrichment_fiche_translation (updated_at)');
        $this->addSql('CREATE INDEX IDX_DAM_MEDIA_UPDATED ON dam_media_asset (updated_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_DAM_MEDIA_UPDATED ON dam_media_asset');
        $this->addSql('DROP INDEX IDX_ENRICHMENT_FICHE_TRANSLATION_UPDATED ON enrichment_fiche_translation');
        $this->addSql('DROP TABLE pim_fiche_enrichment_run');
    }
}
