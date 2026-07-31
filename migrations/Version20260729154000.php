<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260729154000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Regroupe les index Messenger selon le parcours de consommation queue/disponibilité/livraison.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_75EA56E0FB7336F0 ON messenger_messages');
        $this->addSql('DROP INDEX IDX_75EA56E0E3BD61CE ON messenger_messages');
        $this->addSql('DROP INDEX IDX_75EA56E016BA31DB ON messenger_messages');
        $this->addSql(
            'ALTER TABLE messenger_messages CHANGE created_at created_at DATETIME NOT NULL, CHANGE available_at available_at DATETIME NOT NULL, CHANGE delivered_at delivered_at DATETIME DEFAULT NULL',
        );
        $this->addSql(
            'CREATE INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages (queue_name, available_at, delivered_at, id)',
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            'DROP INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages',
        );
        $this->addSql(
            "ALTER TABLE messenger_messages CHANGE created_at created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', CHANGE available_at available_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', CHANGE delivered_at delivered_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'",
        );
        $this->addSql(
            'CREATE INDEX IDX_75EA56E0FB7336F0 ON messenger_messages (queue_name)',
        );
        $this->addSql(
            'CREATE INDEX IDX_75EA56E0E3BD61CE ON messenger_messages (available_at)',
        );
        $this->addSql(
            'CREATE INDEX IDX_75EA56E016BA31DB ON messenger_messages (delivered_at)',
        );
    }
}
