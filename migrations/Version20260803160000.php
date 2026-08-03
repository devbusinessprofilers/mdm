<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260803160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Optimise le recalcul de complétude et historise sa configuration.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX IDX_PIM_FICHE_TYPE_ID ON pim_fiche (type, id)');
        $this->addSql(<<<'SQL'
            CREATE TABLE pim_completeness_configuration_audit (
                id BINARY(16) NOT NULL,
                fiche_type VARCHAR(32) NOT NULL,
                field_code VARCHAR(96) NOT NULL,
                revision INT UNSIGNED NOT NULL,
                actor VARCHAR(180) NOT NULL,
                source VARCHAR(32) NOT NULL,
                before_value JSON DEFAULT NULL,
                after_value JSON DEFAULT NULL,
                changed_at DATETIME NOT NULL,
                INDEX IDX_COMPLETENESS_AUDIT_FIELD_DATE (fiche_type, field_code, changed_at),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE pim_completeness_configuration_audit');
        $this->addSql('DROP INDEX IDX_PIM_FICHE_TYPE_ID ON pim_fiche');
    }
}
