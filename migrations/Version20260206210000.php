<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration for spec-007: Admin Virtual Assistant
 * 
 * Creates three tables:
 * - admin_assistant_conversations: Chat sessions between admin and assistant
 * - admin_assistant_messages: Individual messages in conversations
 * - admin_assistant_actions: Audit log of actions performed via assistant
 */
final class Version20260206210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create admin assistant tables for conversations, messages, and action audit log';
    }

    public function up(Schema $schema): void
    {
        // admin_assistant_conversations table
        $this->addSql('CREATE TABLE admin_assistant_conversations (
            id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\',
            admin_user_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\',
            started_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            ended_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            context_state JSON DEFAULT NULL COMMENT \'Conversational context (current_product, current_user, current_period)\',
            session_id VARCHAR(255) DEFAULT NULL,
            INDEX idx_admin_user (admin_user_id),
            INDEX idx_started_at (started_at),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // admin_assistant_messages table
        $this->addSql('CREATE TABLE admin_assistant_messages (
            id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\',
            conversation_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\',
            sender VARCHAR(20) NOT NULL COMMENT \'admin or assistant\',
            message_text LONGTEXT NOT NULL,
            sent_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            tool_invocations JSON DEFAULT NULL COMMENT \'Array of tool calls made\',
            error_info JSON DEFAULT NULL COMMENT \'Error details if message processing failed\',
            INDEX idx_conversation (conversation_id),
            INDEX idx_sent_at (sent_at),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // admin_assistant_actions table (audit log)
        $this->addSql('CREATE TABLE admin_assistant_actions (
            id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\',
            admin_user_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\',
            conversation_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\',
            action_type VARCHAR(50) NOT NULL COMMENT \'Type of action performed\',
            performed_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            affected_entities JSON DEFAULT NULL COMMENT \'Entity IDs affected by action\',
            action_parameters JSON DEFAULT NULL COMMENT \'Input parameters for action\',
            action_result JSON DEFAULT NULL COMMENT \'Summary of action result\',
            success TINYINT(1) NOT NULL DEFAULT 1,
            error_message TEXT DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent VARCHAR(255) DEFAULT NULL,
            INDEX idx_admin_user (admin_user_id),
            INDEX idx_action_type (action_type),
            INDEX idx_performed_at (performed_at),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Foreign key constraints
        $this->addSql('ALTER TABLE admin_assistant_conversations 
            ADD CONSTRAINT FK_AAC_admin_user 
            FOREIGN KEY (admin_user_id) REFERENCES users (id)');

        $this->addSql('ALTER TABLE admin_assistant_messages 
            ADD CONSTRAINT FK_AAM_conversation 
            FOREIGN KEY (conversation_id) REFERENCES admin_assistant_conversations (id) 
            ON DELETE CASCADE');

        $this->addSql('ALTER TABLE admin_assistant_actions 
            ADD CONSTRAINT FK_AAA_admin_user 
            FOREIGN KEY (admin_user_id) REFERENCES users (id)');

        $this->addSql('ALTER TABLE admin_assistant_actions 
            ADD CONSTRAINT FK_AAA_conversation 
            FOREIGN KEY (conversation_id) REFERENCES admin_assistant_conversations (id) 
            ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE admin_assistant_actions DROP FOREIGN KEY FK_AAA_conversation');
        $this->addSql('ALTER TABLE admin_assistant_actions DROP FOREIGN KEY FK_AAA_admin_user');
        $this->addSql('ALTER TABLE admin_assistant_messages DROP FOREIGN KEY FK_AAM_conversation');
        $this->addSql('ALTER TABLE admin_assistant_conversations DROP FOREIGN KEY FK_AAC_admin_user');

        $this->addSql('DROP TABLE admin_assistant_actions');
        $this->addSql('DROP TABLE admin_assistant_messages');
        $this->addSql('DROP TABLE admin_assistant_conversations');
    }
}
