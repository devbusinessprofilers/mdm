<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260804120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les jobs d’import de fiches ETL et leur rapport d’erreurs.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE etl_import_job (
                id BINARY(16) NOT NULL,
                type VARCHAR(32) NOT NULL,
                original_filename VARCHAR(255) NOT NULL,
                storage_path VARCHAR(255) NOT NULL,
                status VARCHAR(32) NOT NULL,
                total_lines INT UNSIGNED DEFAULT NULL,
                processed_lines INT UNSIGNED DEFAULT 0 NOT NULL,
                created_count INT UNSIGNED DEFAULT 0 NOT NULL,
                updated_count INT UNSIGNED DEFAULT 0 NOT NULL,
                error_count INT UNSIGNED DEFAULT 0 NOT NULL,
                last_processed_line INT UNSIGNED DEFAULT 0 NOT NULL,
                created_by VARCHAR(180) NOT NULL,
                started_at DATETIME DEFAULT NULL,
                finished_at DATETIME DEFAULT NULL,
                failure_message LONGTEXT DEFAULT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE etl_import_job_error (
                id INT UNSIGNED AUTO_INCREMENT NOT NULL,
                job_id BINARY(16) NOT NULL,
                line_number INT UNSIGNED NOT NULL,
                column_name VARCHAR(96) DEFAULT NULL,
                message LONGTEXT NOT NULL,
                created_at DATETIME NOT NULL,
                INDEX IDX_ETL_IMPORT_JOB_ERROR_JOB_LINE (job_id, line_number),
                CONSTRAINT FK_ETL_IMPORT_JOB_ERROR_JOB FOREIGN KEY (job_id) REFERENCES etl_import_job (id) ON DELETE CASCADE,
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE etl_import_job_error');
        $this->addSql('DROP TABLE etl_import_job');
    }
}
