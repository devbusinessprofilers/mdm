<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Monitoring /admin/performance : heartbeats des workers (une ligne par
 * processus, upsertée), série temporelle perf_sample (échantillons à la
 * minute, workers + files) et log_entry (logs Monolog persistés, seul canal
 * lisible par l'app dans tous les environnements).
 */
final class Version20260827120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Tables worker_heartbeat, perf_sample et log_entry (page /admin/performance).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE worker_heartbeat ("
            ."worker_key VARCHAR(128) NOT NULL, "
            ."worker_name VARCHAR(64) NOT NULL, "
            ."hostname VARCHAR(64) NOT NULL, "
            ."pid INT UNSIGNED NOT NULL, "
            ."transports JSON NOT NULL, "
            ."status VARCHAR(16) NOT NULL, "
            ."started_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', "
            ."last_seen_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', "
            ."memory_bytes BIGINT UNSIGNED NOT NULL, "
            ."memory_peak_bytes BIGINT UNSIGNED NOT NULL, "
            ."busy_ms_total BIGINT UNSIGNED NOT NULL, "
            ."handled_total INT UNSIGNED NOT NULL, "
            ."failed_total INT UNSIGNED NOT NULL, "
            ."retried_total INT UNSIGNED NOT NULL, "
            ."current_message_class VARCHAR(255) DEFAULT NULL, "
            ."current_message_since DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', "
            ."message_stats JSON NOT NULL, "
            ."transport_stats JSON NOT NULL, "
            ."INDEX IDX_HEARTBEAT_SEEN (last_seen_at), "
            ."INDEX IDX_HEARTBEAT_NAME_SEEN (worker_name, last_seen_at), "
            ."PRIMARY KEY (worker_key)"
            .") DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`");

        $this->addSql("CREATE TABLE perf_sample ("
            ."id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, "
            ."sampled_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', "
            ."kind VARCHAR(16) NOT NULL, "
            ."subject VARCHAR(128) NOT NULL, "
            ."metrics JSON NOT NULL, "
            ."INDEX IDX_PERF_SAMPLE_TS (sampled_at), "
            ."INDEX IDX_PERF_SAMPLE_SUBJECT (kind, subject, sampled_at), "
            ."PRIMARY KEY (id)"
            .") DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`");

        $this->addSql("CREATE TABLE log_entry ("
            ."id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, "
            ."logged_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', "
            ."channel VARCHAR(64) NOT NULL, "
            ."level SMALLINT UNSIGNED NOT NULL, "
            ."message LONGTEXT NOT NULL, "
            ."context JSON DEFAULT NULL, "
            ."extra JSON DEFAULT NULL, "
            ."request_id VARCHAR(36) DEFAULT NULL, "
            ."hostname VARCHAR(64) DEFAULT NULL, "
            ."INDEX IDX_LOG_LEVEL_TS (level, logged_at), "
            ."INDEX IDX_LOG_CHANNEL_TS (channel, logged_at), "
            ."INDEX IDX_LOG_TS (logged_at), "
            ."PRIMARY KEY (id)"
            .") DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE log_entry');
        $this->addSql('DROP TABLE perf_sample');
        $this->addSql('DROP TABLE worker_heartbeat');
    }
}
