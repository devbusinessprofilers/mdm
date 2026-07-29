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
        $this->addSql('ALTER TABLE pim_fiche_attribute_value DROP FOREIGN KEY FK_PIM_FAV_VALUE');
        $this->addSql('DROP INDEX IDX_80EB6BEBDF522508 ON pim_fiche_attribute_value');
        $this->addSql('DROP INDEX IDX_PIM_ACCES_TYPE ON pim_acces_lieu');
        $this->addSql('DROP INDEX IDX_PIM_RESSOURCE_USAGE ON pim_ressource_lieu');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE INDEX IDX_80EB6BEBDF522508 ON pim_fiche_attribute_value (fiche_id)');
        $this->addSql('ALTER TABLE pim_fiche_attribute_value ADD CONSTRAINT FK_PIM_FAV_VALUE FOREIGN KEY (value_id) REFERENCES pim_attribute_value (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_PIM_ACCES_TYPE ON pim_acces_lieu (type)');
        $this->addSql('CREATE INDEX IDX_PIM_RESSOURCE_USAGE ON pim_ressource_lieu (usage_code)');
    }
}
