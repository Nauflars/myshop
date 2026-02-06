<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add name_es column to products table for Spanish product names
 */
final class Version20260206131936 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add name_es (Spanish name) column to products table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE products ADD name_es VARCHAR(255) DEFAULT NULL');
        
        // Populate with some common Spanish translations
        $this->addSql("UPDATE products SET name_es = 'Portátil Pro 15\"' WHERE name = 'Laptop Pro 15\"'");
        $this->addSql("UPDATE products SET name_es = 'Hub USB-C' WHERE name = 'USB-C Hub'");
        $this->addSql("UPDATE products SET name_es = 'Chaqueta de Invierno' WHERE name = 'Winter Jacket'");
        $this->addSql("UPDATE products SET name_es = 'Diseño de Patrones' WHERE name = 'Design Patterns'");
        $this->addSql("UPDATE products SET name_es = 'Diseño Orientado al Dominio' WHERE name = 'Domain-Driven Design'");
        $this->addSql("UPDATE products SET name_es = 'Reloj de Pared' WHERE name = 'Wall Clock'");
        $this->addSql("UPDATE products SET name_es = 'Zapatos para Correr' WHERE name = 'Running Shoes'");
        $this->addSql("UPDATE products SET name_es = 'Camiseta de Algodón' WHERE name = 'Cotton T-Shirt'");
        $this->addSql("UPDATE products SET name_es = 'El Programador Pragmático' WHERE name = 'The Pragmatic Programmer'");
        $this->addSql("UPDATE products SET name_es = 'Juego de Cojines Decorativos' WHERE name = 'Throw Pillow Set'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE products DROP name_es');
    }
}
