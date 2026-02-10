<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260210140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create user_interactions table for tracking user events (search, product views, clicks, purchases) that trigger embedding updates';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            CREATE TABLE user_interactions (
                id INT AUTO_INCREMENT NOT NULL,
                user_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\',
                event_type ENUM(\'search\', \'product_view\', \'product_click\', \'product_purchase\') NOT NULL,
                search_phrase VARCHAR(255) DEFAULT NULL,
                product_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\',
                occurred_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                metadata JSON DEFAULT NULL,
                created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                processed_to_queue TINYINT(1) DEFAULT 0 NOT NULL,
                PRIMARY KEY(id),
                INDEX idx_user_occurred (user_id, occurred_at DESC),
                INDEX idx_event_type (event_type),
                INDEX idx_processed (processed_to_queue, occurred_at),
                INDEX idx_product (product_id),
                CONSTRAINT FK_user_interactions_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
                CONSTRAINT FK_user_interactions_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE SET NULL
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        ');
        
        // Note: CHECK constraints simplified due to MySQL limitations
        // - Cannot use foreign key columns in CHECK constraints
        // - Cannot use functions like NOW() in CHECK constraints  
        // - Application layer validates business rules
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE user_interactions');
    }
}
