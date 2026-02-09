<?php

declare(strict_types=1);

namespace App\Tests\Application\UseCase\AI;

use App\Application\UseCase\AI\GetCartSummary;
use App\Domain\Entity\Cart;
use App\Domain\Entity\CartItem;
use App\Domain\Entity\Product;
use App\Domain\Entity\User;
use App\Domain\ValueObject\Email;
use App\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for GetCartSummary use case
 */
class GetCartSummaryTest extends TestCase
{
    public function testExecuteReturnsCartSummary(): void
    {
        $user = new User('Test', new Email('test@example.com'), 'hash');
        $cart = new Cart($user);
        
        $product1 = new Product('Laptop', 'Gaming laptop', new Money(150000, 'USD'), 5, 'Electronics');
        $product2 = new Product('Mouse', 'Gaming mouse', new Money(5000, 'USD'), 10, 'Electronics');
        
        $cart->addProduct($product1, 1);
        $cart->addProduct($product2, 2);
        
        $useCase = new GetCartSummary();
        $result = $useCase->execute($cart);
        
        $this->assertFalse($result['isEmpty']);
        $this->assertCount(2, $result['items']);
        $this->assertEquals(2, $result['itemCount']);
        $this->assertEquals('USD', $result['currency']);
        $this->assertGreaterThan(0, $result['total']);
    }

    public function testExecuteWithEmptyCart(): void
    {
        $user = new User('Test', new Email('test@example.com'), 'hash');
        $cart = new Cart($user);
        
        $useCase = new GetCartSummary();
        $result = $useCase->execute($cart);
        
        $this->assertTrue($result['isEmpty']);
        $this->assertEmpty($result['items']);
        $this->assertEquals(0, $result['itemCount']);
        $this->assertEquals(0.0, $result['total']);
    }
}
