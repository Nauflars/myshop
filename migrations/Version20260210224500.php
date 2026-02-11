<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260210224500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add numericId column to users table and unique index';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD numeric_id INT DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_USERS_NUMERIC_ID ON users (numeric_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_USERS_NUMERIC_ID ON users');
        $this->addSql('ALTER TABLE users DROP numeric_id');
    }
}
