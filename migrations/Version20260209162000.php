<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260209162000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create search_history table to track user searches';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            CREATE TABLE search_history (
                id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\',
                user_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\',
                query VARCHAR(500) NOT NULL,
                mode VARCHAR(20) NOT NULL,
                category VARCHAR(100) DEFAULT NULL,
                created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                PRIMARY KEY(id),
                INDEX idx_search_history_user (user_id),
                INDEX idx_search_history_created (created_at),
                CONSTRAINT FK_search_history_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE search_history');
    }
}
