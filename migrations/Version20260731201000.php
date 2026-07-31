<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260731201000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Aligne les noms des index Restaurant avec le mapping Doctrine.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE pim_restaurant RENAME INDEX uniq_restaurant_fiche TO UNIQ_E7162479DF522508',
        );
        $this->addSql(
            'ALTER TABLE pim_restaurant_periode_fermeture RENAME INDEX idx_restaurant_closure_owner TO IDX_34B96979B1E7706E',
        );
        $this->addSql(
            'ALTER TABLE pim_restaurant_salle RENAME INDEX idx_restaurant_room_owner TO IDX_51C896BB1E7706E',
        );
        $this->addSql(
            'ALTER TABLE pim_restaurant_acces RENAME INDEX idx_restaurant_access_owner TO IDX_9B7FCC27B1E7706E',
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE pim_restaurant RENAME INDEX UNIQ_E7162479DF522508 TO uniq_restaurant_fiche',
        );
        $this->addSql(
            'ALTER TABLE pim_restaurant_periode_fermeture RENAME INDEX IDX_34B96979B1E7706E TO idx_restaurant_closure_owner',
        );
        $this->addSql(
            'ALTER TABLE pim_restaurant_salle RENAME INDEX IDX_51C896BB1E7706E TO idx_restaurant_room_owner',
        );
        $this->addSql(
            'ALTER TABLE pim_restaurant_acces RENAME INDEX IDX_9B7FCC27B1E7706E TO idx_restaurant_access_owner',
        );
    }
}
