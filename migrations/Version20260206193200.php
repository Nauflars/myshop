<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration for spec-006: Unanswered Questions Tracking & Admin Panel.
 *
 * Creates the unanswered_questions table to store chatbot questions
 * that could not be answered by the AI agent.
 */
final class Version20260206193200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create unanswered_questions table for tracking chatbot knowledge gaps';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE unanswered_questions (
            id INT AUTO_INCREMENT NOT NULL,
            user_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\',
            question_text LONGTEXT NOT NULL,
            user_role VARCHAR(50) NOT NULL,
            asked_at DATETIME NOT NULL,
            conversation_id VARCHAR(255) DEFAULT NULL,
            reason_category VARCHAR(50) NOT NULL,
            status VARCHAR(50) NOT NULL,
            admin_notes LONGTEXT DEFAULT NULL,
            reviewed_at DATETIME DEFAULT NULL,
            resolved_at DATETIME DEFAULT NULL,
            INDEX IDX_unanswered_user (user_id),
            INDEX idx_status (status),
            INDEX idx_reason (reason_category),
            INDEX idx_asked_at (asked_at),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE unanswered_questions 
            ADD CONSTRAINT FK_unanswered_user 
            FOREIGN KEY (user_id) 
            REFERENCES users (id) 
            ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE unanswered_questions DROP FOREIGN KEY FK_unanswered_user');
        $this->addSql('DROP TABLE unanswered_questions');
    }
}
