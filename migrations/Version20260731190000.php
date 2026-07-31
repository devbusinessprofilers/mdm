<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260731190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Aligne le nom de l index unique Service avec le mapping Doctrine.";
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "ALTER TABLE pim_service_evenementiel " .
                "RENAME INDEX uniq_service_fiche TO UNIQ_866F2218DF522508",
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            "ALTER TABLE pim_service_evenementiel " .
                "RENAME INDEX UNIQ_866F2218DF522508 TO uniq_service_fiche",
        );
    }
}
