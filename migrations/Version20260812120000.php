<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\Uid\Ulid;

final class Version20260812120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crée pim_fiche_relance_planifiee (lot de relances de complétude préparé avant envoi) et le paramètre completude.rappel_auto_actif.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE pim_fiche_relance_planifiee (id BINARY(16) NOT NULL, fiche_id BINARY(16) NOT NULL, prepared_at DATETIME NOT NULL, completeness_at_preparation SMALLINT UNSIGNED NOT NULL, recipient_emails JSON NOT NULL, statut VARCHAR(16) NOT NULL, processed_at DATETIME DEFAULT NULL, motif VARCHAR(255) DEFAULT NULL, INDEX IDX_RELANCE_PLANIFIEE_STATUT_PREPARED (statut, prepared_at), INDEX IDX_RELANCE_PLANIFIEE_FICHE (fiche_id), PRIMARY KEY(id), CONSTRAINT FK_RELANCE_PLANIFIEE_FICHE FOREIGN KEY (fiche_id) REFERENCES pim_fiche (id) ON DELETE CASCADE) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        // Valeur NULL = non surchargé : la variable d'env COMPLETENESS_REMINDER_ENABLED
        // reste la valeur effective, le déploiement est donc un no-op métier.
        $this->addSql(
            'INSERT INTO parametre (id, nom, description, type, valeur, updated_at) VALUES (?, ?, ?, ?, NULL, NULL)',
            [
                (new Ulid())->toBinary(),
                'completude.rappel_auto_actif',
                'Active l’envoi automatique du lot de relances de complétude (lundi 14h). Désactivé, le lot préparé reste consultable et envoyable manuellement depuis /admin/relances-completude.',
                'bool',
            ],
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE pim_fiche_relance_planifiee');
        $this->addSql("DELETE FROM parametre WHERE nom = 'completude.rappel_auto_actif'");
    }
}
