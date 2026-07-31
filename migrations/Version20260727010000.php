<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260727010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute l’outbox transactionnelle et les reçus de déduplication Messenger.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE outbox_message (
                id VARCHAR(26) NOT NULL,
                message_type VARCHAR(255) NOT NULL,
                body LONGTEXT NOT NULL,
                headers JSON NOT NULL,
                status VARCHAR(16) NOT NULL,
                attempts SMALLINT UNSIGNED NOT NULL,
                occurred_at DATETIME NOT NULL,
                available_at DATETIME NOT NULL,
                locked_at DATETIME DEFAULT NULL,
                locked_by VARCHAR(64) DEFAULT NULL,
                published_at DATETIME DEFAULT NULL,
                last_error LONGTEXT DEFAULT NULL,
                INDEX IDX_OUTBOX_PENDING (status, available_at, occurred_at),
                INDEX IDX_OUTBOX_LOCKED (locked_at),
                INDEX IDX_OUTBOX_PUBLISHED (published_at),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB',
        );
        $this->addSql(
            'CREATE TABLE processed_message (
                event_id VARCHAR(26) NOT NULL,
                message_type VARCHAR(255) NOT NULL,
                processed_at DATETIME NOT NULL,
                INDEX IDX_PROCESSED_MESSAGE_DATE (processed_at),
                PRIMARY KEY(event_id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB',
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE processed_message');
        $this->addSql('DROP TABLE outbox_message');
    }
}
