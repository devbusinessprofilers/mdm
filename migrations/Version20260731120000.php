<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Pim\Lov\LieuLovCatalog;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260731120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les documents DAM, les sélections contrôlées et l’audit append-only des Lieux.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIfUnknownLegacyValues(
            'generale_gamme',
            'GENERALE_EVENEMENTS_PREDILECTION',
        );
        $this->abortIfUnknownLegacyValues(
            'dispo_jour_ouverture',
            'DISPO_JOUR_OUVERTURE',
        );
        $this->seedLov(
            'GENERALE_EVENEMENTS_PREDILECTION',
            'Événements de prédilection',
        );
        $this->seedLov('DISPO_JOUR_OUVERTURE', 'Jours d’ouverture');
        $this->migrateLegacyValue(
            'generale_gamme',
            'GENERALE_EVENEMENTS_PREDILECTION',
        );
        $this->migrateLegacyValue(
            'dispo_jour_ouverture',
            'DISPO_JOUR_OUVERTURE',
        );

        $this->addSql(
            "ALTER TABLE dam_media_asset ADD kind VARCHAR(16) DEFAULT 'image' NOT NULL",
        );
        $this->addSql(
            'ALTER TABLE pim_ressource_lieu ADD document_access VARCHAR(16) DEFAULT NULL, ADD publication_status VARCHAR(24) DEFAULT NULL, ADD public_storage_key VARCHAR(1024) DEFAULT NULL',
        );
        $this->addSql(
            "UPDATE pim_ressource_lieu SET usage_code = CASE usage_code WHEN 'rse' THEN 'PJ_JUSTIFICATIF_RSE' WHEN 'plan_general' THEN 'PJ_PLAN_GENERAL' ELSE usage_code END WHERE nature = 'document'",
        );
        $this->addSql(
            "UPDATE pim_ressource_lieu SET document_access = CASE WHEN usage_code IN ('CONFIG_PLAN_SALLE', 'PJ_PLAN_GENERAL', 'PJ_SUPPORT_COMMERCIAUX', 'PJ_JUSTIFICATIF_RSE') THEN 'publishable' ELSE 'private' END, publication_status = 'private' WHERE nature = 'document'",
        );

        $this->addSql(
            <<<'SQL'
                CREATE TABLE audit_revision (
                    id BINARY(16) NOT NULL,
                    fiche_id BINARY(16) NOT NULL,
                    action VARCHAR(64) NOT NULL,
                    source VARCHAR(32) NOT NULL,
                    actor VARCHAR(255) NOT NULL,
                    roles_scopes JSON NOT NULL,
                    correlation_id VARCHAR(128) NOT NULL,
                    created_at DATETIME NOT NULL,
                    INDEX IDX_AUDIT_REVISION_FICHE_DATE (fiche_id, created_at, id),
                    PRIMARY KEY(id)
                ) DEFAULT CHARACTER SET utf8mb4
                  COLLATE `utf8mb4_unicode_ci`
                  ENGINE = InnoDB
                SQL,
        );
        $this->addSql(
            <<<'SQL'
                CREATE TABLE audit_change (
                    id BINARY(16) NOT NULL,
                    revision_id BINARY(16) NOT NULL,
                    path VARCHAR(512) NOT NULL,
                    old_value JSON DEFAULT NULL,
                    new_value JSON DEFAULT NULL,
                    INDEX IDX_6389D2FB1DFA7C8F (revision_id),
                    INDEX IDX_AUDIT_CHANGE_REVISION_PATH (revision_id, path),
                    PRIMARY KEY(id)
                ) DEFAULT CHARACTER SET utf8mb4
                  COLLATE `utf8mb4_unicode_ci`
                  ENGINE = InnoDB
                SQL,
        );
        $this->addSql(
            'ALTER TABLE audit_change ADD CONSTRAINT FK_9A6D1AAFB23DB7B8 FOREIGN KEY (revision_id) REFERENCES audit_revision (id) ON DELETE CASCADE',
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE audit_change');
        $this->addSql('DROP TABLE audit_revision');
        $this->addSql(
            'ALTER TABLE pim_ressource_lieu DROP document_access, DROP publication_status, DROP public_storage_key',
        );
        $this->addSql('ALTER TABLE dam_media_asset DROP kind');
        $this->addSql(
            "DELETE FROM pim_fiche_attribute_value WHERE attribute_code = 'GENERALE_EVENEMENTS_PREDILECTION'",
        );
        $this->addSql(
            "DELETE v FROM pim_attribute_value v INNER JOIN pim_attribute_definition a ON a.id = v.attribute_id WHERE a.code = 'GENERALE_EVENEMENTS_PREDILECTION'",
        );
        $this->addSql(
            "DELETE FROM pim_attribute_definition WHERE code = 'GENERALE_EVENEMENTS_PREDILECTION'",
        );
    }

    private function abortIfUnknownLegacyValues(
        string $column,
        string $attributeCode,
    ): void {
        $choices = LieuLovCatalog::choicesFor($attributeCode);
        $known = [...array_keys($choices), ...array_values($choices)];
        $parameters = implode(', ', array_fill(0, count($known), '?'));
        $rows = $this->connection->fetchFirstColumn(
            sprintf(
                "SELECT DISTINCT %s FROM pim_lieu WHERE %s IS NOT NULL AND TRIM(%s) <> '' AND %s NOT IN (%s) LIMIT 20",
                $column,
                $column,
                $column,
                $column,
                $parameters,
            ),
            $known,
        );
        $this->abortIf(
            [] !== $rows,
            sprintf(
                'Valeurs historiques inconnues dans pim_lieu.%s : %s. Corrigez-les avant de relancer la migration.',
                $column,
                implode(', ', array_map(strval(...), $rows)),
            ),
        );
    }

    private function seedLov(string $attributeCode, string $label): void
    {
        $attributeId = LieuLovCatalog::attributeId($attributeCode);
        $this->addSql(
            "INSERT IGNORE INTO pim_attribute_definition (id, code, label, completeness_weight, translatable, filterable, master_application) VALUES (?, ?, ?, 0, 0, 1, 'pim')",
            [$attributeId, $attributeCode, $label],
        );
        $position = 0;
        foreach (
            LieuLovCatalog::choicesFor($attributeCode) as $code => $choiceLabel
        ) {
            $this->addSql(
                'INSERT IGNORE INTO pim_attribute_value (id, attribute_id, code, label, position) VALUES (?, ?, ?, ?, ?)',
                [
                    LieuLovCatalog::valueId($attributeCode, $code),
                    $attributeId,
                    $code,
                    $choiceLabel,
                    $position,
                ],
            );
            ++$position;
        }
    }

    private function migrateLegacyValue(
        string $column,
        string $attributeCode,
    ): void {
        $this->addSql(
            sprintf(
                "INSERT IGNORE INTO pim_fiche_attribute_value (fiche_id, attribute_code, value_id) SELECT l.fiche_id, '%2\$s', v.id FROM pim_lieu l INNER JOIN pim_attribute_definition a ON a.code = '%2\$s' INNER JOIN pim_attribute_value v ON v.attribute_id = a.id AND (v.code = l.%1\$s OR v.label = l.%1\$s) WHERE l.%1\$s IS NOT NULL AND TRIM(l.%1\$s) <> ''",
                $column,
                $attributeCode,
            ),
        );
    }
}
