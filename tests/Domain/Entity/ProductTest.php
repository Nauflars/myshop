<?php

namespace App\Tests\Domain\Entity;

use App\Domain\Entity\Product;
use App\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

class ProductTest extends TestCase
{
    private Product $product;

    protected function setUp(): void
    {
        $this->product = new Product(
            'Test Product',
            'Test Description',
            new Money(1999, 'USD'),
            50,
            'Electronics'
        );
    }

    public function testProductCreation(): void
    {
        $this->assertNotEmpty($this->product->getId());
        $this->assertEquals('Test Product', $this->product->getName());
        $this->assertEquals('Test Description', $this->product->getDescription());
        $this->assertEquals(1999, $this->product->getPrice()->getAmountInCents());
        $this->assertEquals('USD', $this->product->getPrice()->getCurrency());
        $this->assertEquals(50, $this->product->getStock());
        $this->assertEquals('Electronics', $this->product->getCategory());
    }

    public function testSetName(): void
    {
        $this->product->setName('Updated Product');
        $this->assertEquals('Updated Product', $this->product->getName());
    }

    public function testSetDescription(): void
    {
        $this->product->setDescription('Updated Description');
        $this->assertEquals('Updated Description', $this->product->getDescription());
    }

    public function testSetPrice(): void
    {
        $newPrice = new Money(2999, 'USD');
        $this->product->setPrice($newPrice);
        $this->assertEquals(2999, $this->product->getPrice()->getAmountInCents());
    }

    public function testSetStock(): void
    {
        $this->product->setStock(100);
        $this->assertEquals(100, $this->product->getStock());
    }

    public function testSetStockThrowsExceptionForNegativeValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Stock cannot be negative');
        $this->product->setStock(-10);
    }

    public function testDecrementStock(): void
    {
        $this->product->decrementStock(10);
        $this->assertEquals(40, $this->product->getStock());
    }

    public function testDecrementStockThrowsExceptionForInsufficientStock(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Insufficient stock');
        $this->product->decrementStock(100);
    }

    public function testIncrementStock(): void
    {
        $this->product->incrementStock(25);
        $this->assertEquals(75, $this->product->getStock());
    }

    public function testIsInStock(): void
    {
        $this->assertTrue($this->product->isInStock());

        $this->product->setStock(0);
        $this->assertFalse($this->product->isInStock());
    }

    public function testIsLowStock(): void
    {
        $this->product->setStock(5);
        $this->assertTrue($this->product->isLowStock());

        $this->product->setStock(15);
        $this->assertFalse($this->product->isLowStock());

        $this->product->setStock(0);
        $this->assertFalse($this->product->isLowStock());
    }

    public function testSetCategory(): void
    {
        $this->product->setCategory('Books');
        $this->assertEquals('Books', $this->product->getCategory());
    }

    public function testCreatedAtIsSet(): void
    {
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->product->getCreatedAt());
    }

    public function testUpdatedAtChangesOnModification(): void
    {
        $originalUpdatedAt = $this->product->getUpdatedAt();
        sleep(1);
        $this->product->setName('Modified Product');
        $this->assertGreaterThan($originalUpdatedAt, $this->product->getUpdatedAt());
    }
}
