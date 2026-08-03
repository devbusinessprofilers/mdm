<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260803100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Attribue un code global, automatique et immuable à chaque fiche.';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TEMPORARY TABLE tmp_pim_fiche_code_reassignment (id BINARY(16) NOT NULL, new_code INT UNSIGNED NOT NULL, PRIMARY KEY (id), UNIQUE INDEX UNIQ_TMP_PIM_FICHE_NEW_CODE (new_code)) ENGINE=InnoDB',
        );
        $this->addSql(
            <<<'SQL'
                INSERT INTO tmp_pim_fiche_code_reassignment (id, new_code)
                SELECT candidate.id,
                       (SELECT COALESCE(MAX(existing.code), 0) FROM pim_fiche existing WHERE existing.code > 0)
                           + ROW_NUMBER() OVER (ORDER BY candidate.created_at, candidate.id)
                FROM (
                    SELECT ranked.id, ranked.created_at
                    FROM (
                        SELECT f.id,
                               f.created_at,
                               ROW_NUMBER() OVER (
                                   PARTITION BY f.code
                                   ORDER BY CASE WHEN f.type = 'lieu' THEN 0 ELSE 1 END,
                                            f.created_at,
                                            f.id
                               ) duplicate_rank
                        FROM pim_fiche f
                        WHERE f.code > 0
                    ) ranked
                    WHERE ranked.duplicate_rank > 1

                    UNION ALL

                    SELECT invalid.id, invalid.created_at
                    FROM pim_fiche invalid
                    WHERE invalid.code IS NULL OR invalid.code = 0
                ) candidate
                SQL,
        );
        $this->addSql(
            'UPDATE pim_fiche fiche INNER JOIN tmp_pim_fiche_code_reassignment reassignment ON reassignment.id = fiche.id SET fiche.code = reassignment.new_code',
        );
        $this->addSql('DROP TEMPORARY TABLE tmp_pim_fiche_code_reassignment');
        $this->addSql(
            'ALTER TABLE pim_fiche DROP INDEX UNIQ_PIM_FICHE_TYPE_CODE, MODIFY code INT UNSIGNED NOT NULL, ADD UNIQUE INDEX UNIQ_PIM_FICHE_CODE (code), ADD CONSTRAINT CHK_PIM_FICHE_CODE_POSITIVE CHECK (code > 0)',
        );
        $this->addSql(
            'CREATE TABLE pim_fiche_code_counter (id TINYINT UNSIGNED NOT NULL, last_value INT UNSIGNED NOT NULL, PRIMARY KEY (id), CONSTRAINT CHK_PIM_FICHE_CODE_COUNTER_ID CHECK (id = 1)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        );
        $this->addSql(
            'INSERT INTO pim_fiche_code_counter (id, last_value) SELECT 1, COALESCE(MAX(code), 0) FROM pim_fiche',
        );
        $this->addSql(
            <<<'SQL'
                CREATE TRIGGER TRG_PIM_FICHE_CODE_INSERT
                BEFORE INSERT ON pim_fiche
                FOR EACH ROW
                BEGIN
                    UPDATE pim_fiche_code_counter
                    SET last_value = last_value + 1
                    WHERE id = 1;
                    SET NEW.code = (
                        SELECT counter.last_value
                        FROM pim_fiche_code_counter counter
                        WHERE counter.id = 1
                    );
                END
                SQL,
        );
        $this->addSql(
            <<<'SQL'
                CREATE TRIGGER TRG_PIM_FICHE_CODE_UPDATE
                BEFORE UPDATE ON pim_fiche
                FOR EACH ROW
                BEGIN
                    IF NOT (NEW.code <=> OLD.code) THEN
                        SIGNAL SQLSTATE '45000'
                            SET MESSAGE_TEXT = 'Le code d''une fiche est immuable.';
                    END IF;
                END
                SQL,
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TRIGGER IF EXISTS TRG_PIM_FICHE_CODE_UPDATE');
        $this->addSql('DROP TRIGGER IF EXISTS TRG_PIM_FICHE_CODE_INSERT');
        $this->addSql('DROP TABLE pim_fiche_code_counter');
        $this->addSql(
            'ALTER TABLE pim_fiche DROP CONSTRAINT CHK_PIM_FICHE_CODE_POSITIVE, DROP INDEX UNIQ_PIM_FICHE_CODE, MODIFY code INT UNSIGNED DEFAULT NULL, ADD UNIQUE INDEX UNIQ_PIM_FICHE_TYPE_CODE (type, code)',
        );
    }
}
