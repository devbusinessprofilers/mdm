<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Pim\Lov\LieuLovCatalog;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260729151000 extends AbstractMigration
{
    /** @var array<string, string> */
    private const LOV_COLUMNS = [
        'GENERALE_TYPOLOGIE' => 'generale_typologie',
        'GENERALE_CHAINES_GROUPE_HOT' => 'generale_chaines_groupe_hot',
        'TA_THEMATIQUE' => 'ta_thematique',
        'TA_CADRE_ENV' => 'ta_cadre_env',
        'TA_AMBIANCE' => 'ta_ambiance',
        'EQUIPEMENTS' => 'equipements',
        'SERVICES' => 'services',
        'TECHNIQUE_REUNION' => 'technique_reunion',
        'INSTALLATION' => 'installation',
        'BIEN_ETRE' => 'bien_etre',
        'ACHAT_RESPONSABLE' => 'achat_responsable',
        'IMPACT_ENV' => 'impact_env',
        'IMPACT_SOCIAL' => 'impact_social',
        'VOLUME_ACHAT_CAT_ESAT_STPA' => 'volume_achat_cat_esat_stpa',
        'MOBILITE' => 'mobilite',
        'CERTIFICATION' => 'certification',
        'TYPE_CUISINE' => 'type_cuisine',
        'SERVICE_RESTAURATION' => 'service_restauration',
        'MODE_PAIEMENT_CARTE_LISTE' => 'mode_paiement_carte_liste',
        'SITE_PREMIUM' => 'site_premium',
    ];

    public function getDescription(): string
    {
        return 'Crée Fiche, compacte les ULID, normalise les LOV et ajoute les index prévus pour un million de fiches.';
    }

    public function up(Schema $schema): void
    {
        $this->backfillBinaryIds();

        $this->addSql(
            'UPDATE pim_lieu l LEFT JOIN pim_localisation loc ON loc.id = l.localisation_id SET l.localisation_id_binary = loc.id_binary',
        );
        foreach (
            [
                'pim_salle',
                'pim_periode_fermeture',
                'pim_acces_lieu',
                'pim_ressource_lieu',
            ] as $table
        ) {
            $this->addSql(
                sprintf(
                    'UPDATE %1$s child INNER JOIN pim_lieu l ON l.id = child.lieu_id SET child.lieu_id_binary = l.id_binary',
                    $table,
                ),
            );
        }
        $this->addSql(
            'UPDATE pim_ressource_lieu r LEFT JOIN pim_salle s ON s.id = r.salle_id SET r.salle_id_binary = s.id_binary',
        );

        $this->createFicheTables();
        $this->seedLovRegistry();
        $this->migrateLovValues();

        $this->addSql(
            "INSERT INTO pim_fiche (id, type, code, label, status, completeness, version, published_at, archived_at, created_at, updated_at, localisation_id) SELECT id_binary, 'lieu', code, label, IF(published = 1, 'publiee', 'en_cours'), 0, 1, IF(published = 1, updated_at, NULL), NULL, created_at, updated_at, localisation_id_binary FROM pim_lieu",
        );
        $this->addSql(
            "INSERT INTO pim_fiche_search (fiche_id, content, source_version, indexed_at) SELECT f.id, TRIM(CONCAT_WS(' ', f.code, f.label, loc.rue_postale, loc.code_postal, loc.ville, loc.pays, l.desc_generale)), f.version, NOW() FROM pim_fiche f INNER JOIN pim_lieu l ON l.id_binary = f.id LEFT JOIN pim_localisation loc ON loc.id_binary = f.localisation_id",
        );

        $this->swapBinaryColumnsAndIndexes();
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException(
            'Le retour aux ULID texte et aux LOV JSON ferait perdre les optimisations et les modifications ultérieures.',
        );
    }

    private function backfillBinaryIds(): void
    {
        foreach (
            [
                'pim_lieu',
                'pim_localisation',
                'pim_salle',
                'pim_periode_fermeture',
                'pim_acces_lieu',
                'pim_ressource_lieu',
            ] as $table
        ) {
            $this->addSql(
                sprintf(
                    'UPDATE %1$s SET id_binary = UNHEX(CONCAT(LPAD(CONV(%2$s, 32, 16), 12, \'0\'), LPAD(CONV(%3$s, 32, 16), 10, \'0\'), LPAD(CONV(%4$s, 32, 16), 10, \'0\'))) WHERE id_binary IS NULL',
                    $table,
                    $this->toBase32Expression('SUBSTRING(id, 1, 10)'),
                    $this->toBase32Expression('SUBSTRING(id, 11, 8)'),
                    $this->toBase32Expression('SUBSTRING(id, 19, 8)'),
                ),
            );
        }
    }

    /** Converts Crockford's ULID alphabet to the alphabet understood by SQL CONV(..., 32, 16). */
    private function toBase32Expression(string $expression): string
    {
        foreach (
            [
                'J' => 'I',
                'K' => 'J',
                'M' => 'K',
                'N' => 'L',
                'P' => 'M',
                'Q' => 'N',
                'R' => 'O',
                'S' => 'P',
                'T' => 'Q',
                'V' => 'R',
                'W' => 'S',
                'X' => 'T',
                'Y' => 'U',
                'Z' => 'V',
            ] as $from => $to
        ) {
            $expression = sprintf(
                "REPLACE(%s, '%s', '%s')",
                $expression,
                $from,
                $to,
            );
        }

        return $expression;
    }

    private function createFicheTables(): void
    {
        $this->addSql(
            'ALTER TABLE pim_localisation ADD country_code VARCHAR(2) DEFAULT NULL, ADD ville_normalisee VARCHAR(255) DEFAULT NULL, ADD address_fingerprint VARBINARY(32) DEFAULT NULL',
        );
        $this->addSql(
            "UPDATE pim_localisation SET country_code = CASE WHEN LOWER(pays) IN ('france', 'fr') THEN 'FR' ELSE NULL END, ville_normalisee = LOWER(TRIM(ville)), address_fingerprint = UNHEX(SHA2(LOWER(CONCAT_WS('|', pays, code_postal, ville, rue_postale)), 256)) WHERE pays IS NOT NULL OR code_postal IS NOT NULL OR ville IS NOT NULL OR rue_postale IS NOT NULL",
        );
        $this->addSql(
            'CREATE TABLE pim_fiche (id BINARY(16) NOT NULL, type VARCHAR(32) NOT NULL, code INT UNSIGNED DEFAULT NULL, label VARCHAR(255) DEFAULT NULL, status VARCHAR(16) NOT NULL, completeness SMALLINT UNSIGNED DEFAULT 0 NOT NULL, version INT UNSIGNED DEFAULT 1 NOT NULL, published_at DATETIME DEFAULT NULL, archived_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, localisation_id BINARY(16) DEFAULT NULL, UNIQUE INDEX UNIQ_4486C089C68BE09C (localisation_id), INDEX IDX_PIM_FICHE_TYPE_UPDATED (type, updated_at, id), INDEX IDX_PIM_FICHE_TYPE_STATUS_UPDATED (type, status, updated_at, id), INDEX IDX_PIM_FICHE_STATUS_UPDATED (status, updated_at, id), UNIQUE INDEX UNIQ_PIM_FICHE_TYPE_CODE (type, code), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`',
        );
        $this->addSql(
            'CREATE TABLE pim_attribute_definition (id INT UNSIGNED NOT NULL, code VARCHAR(64) NOT NULL, label VARCHAR(255) NOT NULL, completeness_weight SMALLINT UNSIGNED DEFAULT 0 NOT NULL, translatable TINYINT DEFAULT 0 NOT NULL, filterable TINYINT DEFAULT 1 NOT NULL, master_application VARCHAR(32) DEFAULT \'pim\' NOT NULL, UNIQUE INDEX UNIQ_PIM_ATTRIBUTE_CODE (code), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`',
        );
        $this->addSql(
            'CREATE TABLE pim_attribute_value (id INT UNSIGNED NOT NULL, code VARCHAR(96) NOT NULL, label VARCHAR(255) NOT NULL, position SMALLINT UNSIGNED DEFAULT 0 NOT NULL, attribute_id INT UNSIGNED NOT NULL, INDEX IDX_B37A5BC3B6E62EFA (attribute_id), INDEX IDX_PIM_ATTRIBUTE_VALUES (attribute_id, position, id), UNIQUE INDEX UNIQ_PIM_ATTRIBUTE_VALUE_CODE (attribute_id, code), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`',
        );
        $this->addSql(
            'CREATE TABLE pim_fiche_attribute_value (attribute_code VARCHAR(64) NOT NULL, value_id INT UNSIGNED NOT NULL, fiche_id BINARY(16) NOT NULL, INDEX IDX_PIM_FAV_VALUE_FICHE (value_id, fiche_id), PRIMARY KEY (fiche_id, value_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`',
        );
        $this->addSql(
            'CREATE TABLE pim_fiche_search (content LONGTEXT NOT NULL, source_version INT UNSIGNED NOT NULL, indexed_at DATETIME NOT NULL, fiche_id BINARY(16) NOT NULL, FULLTEXT INDEX FTX_PIM_FICHE_SEARCH (content), PRIMARY KEY (fiche_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`',
        );
    }

    private function seedLovRegistry(): void
    {
        foreach (LieuLovCatalog::allChoices() as $attributeCode => $choices) {
            $this->addSql(
                'INSERT INTO pim_attribute_definition (id, code, label, completeness_weight, translatable, filterable, master_application) VALUES (?, ?, ?, 0, 0, 1, \'pim\')',
                [
                    LieuLovCatalog::attributeId($attributeCode),
                    $attributeCode,
                    $attributeCode,
                ],
            );
            $position = 0;
            foreach ($choices as $valueCode => $label) {
                $this->addSql(
                    'INSERT INTO pim_attribute_value (id, attribute_id, code, label, position) VALUES (?, ?, ?, ?, ?)',
                    [
                        LieuLovCatalog::valueId($attributeCode, $valueCode),
                        LieuLovCatalog::attributeId($attributeCode),
                        $valueCode,
                        $label,
                        $position++,
                    ],
                );
            }
        }
    }

    private function migrateLovValues(): void
    {
        foreach (self::LOV_COLUMNS as $attributeCode => $column) {
            $this->addSql(
                sprintf(
                    "INSERT IGNORE INTO pim_fiche_attribute_value (fiche_id, attribute_code, value_id) SELECT l.id_binary, '%1\$s', v.id FROM pim_lieu l JOIN JSON_TABLE(l.%2\$s, '\$[*]' COLUMNS (value_code VARCHAR(96) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci PATH '\$')) j JOIN pim_attribute_value v ON v.code = j.value_code JOIN pim_attribute_definition a ON a.id = v.attribute_id AND a.code = '%1\$s'",
                    $attributeCode,
                    $column,
                ),
            );
        }
    }

    private function swapBinaryColumnsAndIndexes(): void
    {
        $this->addSql(
            'ALTER TABLE pim_lieu DROP FOREIGN KEY FK_D766A114C68BE09C',
        );
        $this->addSql(
            'ALTER TABLE pim_salle DROP FOREIGN KEY FK_460272AD6AB213CC',
        );
        $this->addSql(
            'ALTER TABLE pim_periode_fermeture DROP FOREIGN KEY FK_FFBA7A386AB213CC',
        );
        $this->addSql(
            'ALTER TABLE pim_acces_lieu DROP FOREIGN KEY FK_ACECD356AB213CC',
        );
        $this->addSql(
            'ALTER TABLE pim_ressource_lieu DROP FOREIGN KEY FK_586964B06AB213CC, DROP FOREIGN KEY FK_586964B0DC304035',
        );

        $this->addSql(
            'ALTER TABLE pim_lieu DROP PRIMARY KEY, DROP id, CHANGE id_binary id BINARY(16) NOT NULL, ADD fiche_id BINARY(16) DEFAULT NULL, DROP code, DROP label, DROP generale_typologie, DROP generale_chaines_groupe_hot, DROP ta_thematique, DROP ta_cadre_env, DROP ta_ambiance, DROP equipements, DROP services, DROP technique_reunion, DROP installation, DROP bien_etre, DROP achat_responsable, DROP impact_env, DROP impact_social, DROP volume_achat_cat_esat_stpa, DROP mobilite, DROP certification, DROP type_cuisine, DROP service_restauration, DROP mode_paiement_carte_liste, DROP site_premium, DROP published, DROP localisation_id, DROP localisation_id_binary, ADD PRIMARY KEY (id)',
        );
        $this->addSql('UPDATE pim_lieu SET fiche_id = id');
        $this->addSql(
            'ALTER TABLE pim_lieu MODIFY fiche_id BINARY(16) NOT NULL, ADD UNIQUE INDEX UNIQ_D766A114DF522508 (fiche_id)',
        );
        $this->swapTable('pim_localisation', []);
        $this->swapTable('pim_salle', ['lieu_id']);
        $this->swapTable('pim_periode_fermeture', ['lieu_id']);
        $this->swapTable('pim_acces_lieu', ['lieu_id']);
        $this->swapTable('pim_ressource_lieu', ['lieu_id', 'salle_id']);

        $this->addSql(
            'ALTER TABLE pim_fiche ADD CONSTRAINT FK_4486C089C68BE09C FOREIGN KEY (localisation_id) REFERENCES pim_localisation (id) ON DELETE SET NULL',
        );
        $this->addSql(
            'ALTER TABLE pim_lieu ADD CONSTRAINT FK_D766A114DF522508 FOREIGN KEY (fiche_id) REFERENCES pim_fiche (id) ON DELETE CASCADE',
        );
        $this->addSql(
            'ALTER TABLE pim_salle ADD CONSTRAINT FK_460272AD6AB213CC FOREIGN KEY (lieu_id) REFERENCES pim_lieu (id) ON DELETE CASCADE',
        );
        $this->addSql(
            'ALTER TABLE pim_periode_fermeture ADD CONSTRAINT FK_FFBA7A386AB213CC FOREIGN KEY (lieu_id) REFERENCES pim_lieu (id) ON DELETE CASCADE',
        );
        $this->addSql(
            'ALTER TABLE pim_acces_lieu ADD CONSTRAINT FK_ACECD356AB213CC FOREIGN KEY (lieu_id) REFERENCES pim_lieu (id) ON DELETE CASCADE',
        );
        $this->addSql(
            'ALTER TABLE pim_ressource_lieu ADD CONSTRAINT FK_586964B06AB213CC FOREIGN KEY (lieu_id) REFERENCES pim_lieu (id) ON DELETE CASCADE, ADD CONSTRAINT FK_586964B0DC304035 FOREIGN KEY (salle_id) REFERENCES pim_salle (id) ON DELETE CASCADE',
        );
        $this->addSql(
            'ALTER TABLE pim_attribute_value ADD CONSTRAINT FK_B37A5BC3B6E62EFA FOREIGN KEY (attribute_id) REFERENCES pim_attribute_definition (id) ON DELETE CASCADE',
        );
        $this->addSql(
            'ALTER TABLE pim_fiche_attribute_value ADD CONSTRAINT FK_80EB6BEBDF522508 FOREIGN KEY (fiche_id) REFERENCES pim_fiche (id) ON DELETE CASCADE',
        );
        $this->addSql(
            'ALTER TABLE pim_fiche_search ADD CONSTRAINT FK_6B7507E9DF522508 FOREIGN KEY (fiche_id) REFERENCES pim_fiche (id) ON DELETE CASCADE',
        );

        $this->addSql(
            'ALTER TABLE pim_localisation ADD INDEX IDX_PIM_LOCALISATION_FINGERPRINT (address_fingerprint), ADD INDEX IDX_PIM_LOCALISATION_POSTAL (country_code, code_postal, id), ADD INDEX IDX_PIM_LOCALISATION_CITY (country_code, ville_normalisee, id)',
        );
        $this->addSql(
            'CREATE INDEX IDX_PIM_SALLE_ORDERED ON pim_salle (lieu_id, position, id)',
        );
        $this->addSql(
            'CREATE INDEX IDX_PIM_CLOSURE_ORDERED ON pim_periode_fermeture (lieu_id, date_debut, id)',
        );
        $this->addSql(
            'ALTER TABLE pim_acces_lieu ADD INDEX IDX_PIM_ACCESS_ORDERED (lieu_id, type, position, id), ADD INDEX IDX_PIM_ACCESS_REVERSE (type, lieu_id)',
        );
        $this->addSql(
            'ALTER TABLE pim_ressource_lieu ADD INDEX IDX_PIM_RESOURCE_LIEU_ORDERED (lieu_id, position, id), ADD INDEX IDX_PIM_RESOURCE_SALLE_ORDERED (salle_id, position, id), ADD INDEX IDX_PIM_RESOURCE_USAGE (usage_code, lieu_id)',
        );
        $this->addSql('DROP INDEX IDX_PIM_ACCES_TYPE ON pim_acces_lieu');
        $this->addSql(
            'DROP INDEX IDX_PIM_RESSOURCE_USAGE ON pim_ressource_lieu',
        );
    }

    /** @param list<string> $foreignKeys */
    private function swapTable(string $table, array $foreignKeys): void
    {
        $parts = [
            'DROP PRIMARY KEY',
            'DROP id',
            'CHANGE id_binary id BINARY(16) NOT NULL',
        ];
        foreach ($foreignKeys as $foreignKey) {
            $parts[] = sprintf('DROP %s', $foreignKey);
            $parts[] = sprintf(
                'CHANGE %s_binary %s BINARY(16) %s',
                $foreignKey,
                $foreignKey,
                'salle_id' === $foreignKey ? 'DEFAULT NULL' : 'NOT NULL',
            );
        }
        $parts[] = 'ADD PRIMARY KEY (id)';
        $this->addSql(
            sprintf('ALTER TABLE %s %s', $table, implode(', ', $parts)),
        );
    }
}
