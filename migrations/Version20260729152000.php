<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260729152000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Supprime les index devenus redondants après la normalisation des fiches et des LOV.';
    }

    public function up(Schema $schema): void
    {
        // Fresh installations never had these two legacy objects, while upgraded
        // databases can still contain them. Keep the migration valid for both paths.
        if (
            $this->foreignKeyExists(
                'pim_fiche_attribute_value',
                'FK_PIM_FAV_VALUE',
            )
        ) {
            $this->addSql(
                'ALTER TABLE pim_fiche_attribute_value DROP FOREIGN KEY FK_PIM_FAV_VALUE',
            );
        }
        if (
            $this->indexExists(
                'pim_fiche_attribute_value',
                'IDX_80EB6BEBDF522508',
            )
        ) {
            $this->addSql(
                'DROP INDEX IDX_80EB6BEBDF522508 ON pim_fiche_attribute_value',
            );
        }
        if ($this->indexExists('pim_acces_lieu', 'IDX_PIM_ACCES_TYPE')) {
            $this->addSql('DROP INDEX IDX_PIM_ACCES_TYPE ON pim_acces_lieu');
        }
        if (
            $this->indexExists('pim_ressource_lieu', 'IDX_PIM_RESSOURCE_USAGE')
        ) {
            $this->addSql(
                'DROP INDEX IDX_PIM_RESSOURCE_USAGE ON pim_ressource_lieu',
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            'CREATE INDEX IDX_80EB6BEBDF522508 ON pim_fiche_attribute_value (fiche_id)',
        );
        $this->addSql(
            'ALTER TABLE pim_fiche_attribute_value ADD CONSTRAINT FK_PIM_FAV_VALUE FOREIGN KEY (value_id) REFERENCES pim_attribute_value (id) ON DELETE CASCADE',
        );
        $this->addSql(
            'CREATE INDEX IDX_PIM_ACCES_TYPE ON pim_acces_lieu (type)',
        );
        $this->addSql(
            'CREATE INDEX IDX_PIM_RESSOURCE_USAGE ON pim_ressource_lieu (usage_code)',
        );
    }

    private function foreignKeyExists(string $table, string $constraint): bool
    {
        return false !==
            $this->connection->fetchOne(
                'SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = \'FOREIGN KEY\'',
                [$table, $constraint],
            );
    }

    private function indexExists(string $table, string $index): bool
    {
        return false !==
            $this->connection->fetchOne(
                'SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
                [$table, $index],
            );
    }
}
