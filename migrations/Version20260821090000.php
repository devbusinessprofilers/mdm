<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\Uid\Ulid;

final class Version20260821090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Détection de doublons de textes : empreintes, bandes SimHash, alertes et paramètres.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE pim_text_fingerprint (id BINARY(16) NOT NULL COMMENT '(DC2Type:ulid)', fiche_id VARCHAR(26) NOT NULL, fiche_type VARCHAR(32) NOT NULL, field_path VARCHAR(191) NOT NULL, field_label VARCHAR(255) NOT NULL, exact_hash VARCHAR(64) NOT NULL, simhash VARCHAR(16) NOT NULL, length INT NOT NULL, snippet VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', UNIQUE INDEX UNIQ_PIM_TEXT_FP_FIELD (fiche_id, field_path), INDEX IDX_PIM_TEXT_FP_EXACT (exact_hash), INDEX IDX_PIM_TEXT_FP_TYPE (fiche_type), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql("CREATE TABLE pim_text_simhash_band (fingerprint_id BINARY(16) NOT NULL, band_index SMALLINT NOT NULL, band_value SMALLINT NOT NULL, PRIMARY KEY(fingerprint_id, band_index), INDEX IDX_PIM_TEXT_BAND_LOOKUP (band_index, band_value), CONSTRAINT FK_PIM_TEXT_BAND_FP FOREIGN KEY (fingerprint_id) REFERENCES pim_text_fingerprint (id) ON DELETE CASCADE) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql("CREATE TABLE pim_text_duplicate_alert (id BINARY(16) NOT NULL COMMENT '(DC2Type:ulid)', fingerprint_id BINARY(16) NOT NULL, duplicate_of_id BINARY(16) NOT NULL, fiche_id VARCHAR(26) NOT NULL, fiche_type VARCHAR(32) NOT NULL, field_path VARCHAR(191) NOT NULL, kind VARCHAR(16) NOT NULL, distance INT DEFAULT NULL, status VARCHAR(16) NOT NULL, reviewed_by VARCHAR(26) DEFAULT NULL, reviewed_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', UNIQUE INDEX UNIQ_PIM_TEXT_DUPLICATE_FP (fingerprint_id), INDEX IDX_PIM_TEXT_DUPLICATE_STATUS_CREATED (status, created_at), INDEX IDX_PIM_TEXT_DUPLICATE_FICHE_TYPE (fiche_type, status), INDEX IDX_PIM_TEXT_DUPLICATE_REFERENCE (duplicate_of_id), PRIMARY KEY(id), CONSTRAINT FK_PIM_TEXT_DUPLICATE_FP FOREIGN KEY (fingerprint_id) REFERENCES pim_text_fingerprint (id) ON DELETE CASCADE, CONSTRAINT FK_PIM_TEXT_DUPLICATE_REF FOREIGN KEY (duplicate_of_id) REFERENCES pim_text_fingerprint (id) ON DELETE CASCADE) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");

        // Catalogue : valeur NULL = non surchargé, la variable d'env reste la
        // valeur effective (voir Version20260812100000).
        $catalogue = [
            ['pim.longueur_min_texte_doublon', 'int', 'Longueur minimale (caractères normalisés) d’un champ de texte pour être analysé en détection de doublons.'],
            ['pim.seuil_distance_simhash', 'int', 'Distance SimHash maximale (bits) pour signaler deux textes comme quasi-doublons (plus élevé = plus sensible).'],
        ];
        foreach ($catalogue as [$nom, $type, $description]) {
            $this->addSql(
                'INSERT INTO parametre (id, nom, description, type, valeur, updated_at) VALUES (?, ?, ?, ?, NULL, NULL)',
                [(new Ulid())->toBinary(), $nom, $description, $type],
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM parametre WHERE nom IN ('pim.longueur_min_texte_doublon', 'pim.seuil_distance_simhash')");
        $this->addSql('DROP TABLE pim_text_duplicate_alert');
        $this->addSql('DROP TABLE pim_text_simhash_band');
        $this->addSql('DROP TABLE pim_text_fingerprint');
    }
}
