<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Normalize all product currencies to USD
 */
final class Version20260207150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Update all products to use USD as currency';
    }

    public function up(Schema $schema): void
    {
        // Update all products to USD currency
        $this->addSql("UPDATE products SET currency = 'USD' WHERE currency != 'USD'");
    }

    public function down(Schema $schema): void
    {
        // This migration is not reversible as we lose the original currency information
        $this->addSql("SELECT 1");
    }
}
