<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * T085: Add database indexes on Product.updated_at for sync queries.
 *
 * Optimizes ProductEmbedding sync performance by adding indexes
 * for efficient query of recently updated products
 */
final class Version20260207120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add indexes for semantic search embed ding sync performance (T085)';
    }

    public function up(Schema $schema): void
    {
        // Add index on Product.updated_at for efficient "recently updated" queries
        $this->addSql('CREATE INDEX idx_product_updated_at ON product (updated_at)');

        // Add composite index on category + updated_at for filtered sync
        $this->addSql('CREATE INDEX idx_product_category_updated_at ON product (category, updated_at)');

        // Add index on created_at for initial sync operations
        $this->addSql('CREATE INDEX idx_product_created_at ON product (created_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_product_updated_at ON product');
        $this->addSql('DROP INDEX idx_product_category_updated_at ON product');
        $this->addSql('DROP INDEX idx_product_created_at ON product');
    }
}
