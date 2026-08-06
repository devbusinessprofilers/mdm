<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260806150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Le trigger de code fiche respecte un code fourni explicitement (reprise legacy : code = Id syspad).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TRIGGER IF EXISTS TRG_PIM_FICHE_CODE_INSERT');
        $this->addSql(<<<'SQL'
            CREATE TRIGGER TRG_PIM_FICHE_CODE_INSERT
            BEFORE INSERT ON pim_fiche
            FOR EACH ROW
            BEGIN
                IF NEW.code IS NULL OR NEW.code = 0 THEN
                    UPDATE pim_fiche_code_counter
                    SET last_value = last_value + 1
                    WHERE id = 1;
                    SET NEW.code = (
                        SELECT counter.last_value
                        FROM pim_fiche_code_counter counter
                        WHERE counter.id = 1
                    );
                ELSE
                    UPDATE pim_fiche_code_counter
                    SET last_value = GREATEST(last_value, NEW.code)
                    WHERE id = 1;
                END IF;
            END
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TRIGGER IF EXISTS TRG_PIM_FICHE_CODE_INSERT');
        $this->addSql(<<<'SQL'
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
            SQL);
    }
}
