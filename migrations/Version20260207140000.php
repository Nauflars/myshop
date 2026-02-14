<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * T095: Dead Letter Queue for failed embedding sync jobs.
 *
 * Creates failed_embedding_jobs table to track embedding sync failures
 * Enables retry mechanism and failure analysis
 */
final class Version20260207140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'T095: Create failed_embedding_jobs table for dead letter queue';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            CREATE TABLE failed_embedding_jobs (
                id INT AUTO_INCREMENT NOT NULL,
                product_id VARCHAR(36) NOT NULL COMMENT \'UUID of the product\',
                operation VARCHAR(20) NOT NULL COMMENT \'create, update, or delete\',
                error_message TEXT NOT NULL,
                error_trace TEXT DEFAULT NULL,
                payload JSON NOT NULL COMMENT \'Product data snapshot at time of failure\',
                attempts INT NOT NULL DEFAULT 0,
                failed_at DATETIME NOT NULL,
                last_retry_at DATETIME DEFAULT NULL,
                retry_after DATETIME DEFAULT NULL COMMENT \'Do not retry before this time\',
                status VARCHAR(20) NOT NULL DEFAULT \'failed\' COMMENT \'failed, retrying, resolved, abandoned\',
                resolved_at DATETIME DEFAULT NULL,
                INDEX idx_product_id (product_id),
                INDEX idx_status (status),
                INDEX idx_retry_after (retry_after),
                INDEX idx_failed_at (failed_at),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE failed_embedding_jobs');
    }
}
