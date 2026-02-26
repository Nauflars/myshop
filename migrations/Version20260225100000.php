<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260225100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add shipping_address JSON column to orders table for mobile checkout';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE orders ADD shipping_address JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE orders DROP COLUMN shipping_address');
    }
}
