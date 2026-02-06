<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260206120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create conversations and conversation_messages tables for spec-003 chat improvements';
    }

    public function up(Schema $schema): void
    {
        // Create conversations table
        $this->addSql('CREATE TABLE conversations (
            id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\',
            user_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\',
            title VARCHAR(255) NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX idx_user_created (user_id, created_at),
            PRIMARY KEY(id),
            CONSTRAINT FK_conversations_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Create conversation_messages table
        $this->addSql('CREATE TABLE conversation_messages (
            id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\',
            conversation_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\',
            role VARCHAR(20) NOT NULL,
            content LONGTEXT NOT NULL,
            tool_calls JSON DEFAULT NULL,
            timestamp DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX idx_conversation_timestamp (conversation_id, timestamp),
            PRIMARY KEY(id),
            CONSTRAINT FK_conversation_messages_conversation FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE conversation_messages');
        $this->addSql('DROP TABLE conversations');
    }
}
