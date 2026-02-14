<?php

namespace App\Tests\Domain\ValueObject;

use App\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function testMoneyCreation(): void
    {
        $money = new Money(1999, 'USD');

        $this->assertEquals(1999, $money->getAmountInCents());
        $this->assertEquals('USD', $money->getCurrency());
        $this->assertEquals(19.99, $money->getAmountAsDecimal());
    }

    public function testMoneyCreationWithDefaultCurrency(): void
    {
        $money = new Money(2500);

        $this->assertEquals(2500, $money->getAmountInCents());
        $this->assertEquals('USD', $money->getCurrency());
    }

    public function testMoneyCreationThrowsExceptionForNegativeAmount(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount cannot be negative');
        new Money(-100, 'USD');
    }

    public function testMoneyCreationThrowsExceptionForInvalidCurrency(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Currency must be a 3-letter ISO code');
        new Money(1000, 'US');
    }

    public function testFromDecimal(): void
    {
        $money = Money::fromDecimal(19.99, 'USD');

        $this->assertEquals(1999, $money->getAmountInCents());
        $this->assertEquals('USD', $money->getCurrency());
    }

    public function testFormat(): void
    {
        $usd = new Money(1999, 'USD');
        $this->assertEquals('$19.99', $usd->format());

        $eur = new Money(2500, 'EUR');
        $this->assertEquals('€25.00', $eur->format());

        $gbp = new Money(3099, 'GBP');
        $this->assertEquals('£30.99', $gbp->format());
    }

    public function testFormatWithUnknownCurrency(): void
    {
        $money = new Money(1999, 'JPY');
        $this->assertEquals('JPY19.99', $money->format());
    }

    public function testAdd(): void
    {
        $money1 = new Money(1000, 'USD');
        $money2 = new Money(500, 'USD');
        $result = $money1->add($money2);

        $this->assertEquals(1500, $result->getAmountInCents());
        $this->assertEquals('USD', $result->getCurrency());
    }

    public function testAddThrowsExceptionForDifferentCurrencies(): void
    {
        $usd = new Money(1000, 'USD');
        $eur = new Money(500, 'EUR');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot operate on different currencies');
        $usd->add($eur);
    }

    public function testSubtract(): void
    {
        $money1 = new Money(1000, 'USD');
        $money2 = new Money(300, 'USD');
        $result = $money1->subtract($money2);

        $this->assertEquals(700, $result->getAmountInCents());
        $this->assertEquals('USD', $result->getCurrency());
    }

    public function testSubtractThrowsExceptionForDifferentCurrencies(): void
    {
        $usd = new Money(1000, 'USD');
        $eur = new Money(300, 'EUR');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot operate on different currencies');
        $usd->subtract($eur);
    }

    public function testMultiply(): void
    {
        $money = new Money(500, 'USD');
        $result = $money->multiply(3);

        $this->assertEquals(1500, $result->getAmountInCents());
        $this->assertEquals('USD', $result->getCurrency());
    }

    public function testEquals(): void
    {
        $money1 = new Money(1000, 'USD');
        $money2 = new Money(1000, 'USD');
        $money3 = new Money(2000, 'USD');
        $money4 = new Money(1000, 'EUR');

        $this->assertTrue($money1->equals($money2));
        $this->assertFalse($money1->equals($money3));
        $this->assertFalse($money1->equals($money4));
    }

    public function testToString(): void
    {
        $money = new Money(1999, 'USD');
        $this->assertEquals('$19.99', (string) $money);
    }

    public function testCurrencyIsUppercased(): void
    {
        $money = new Money(1000, 'usd');
        $this->assertEquals('USD', $money->getCurrency());
    }

    public function testImmutability(): void
    {
        $original = new Money(1000, 'USD');
        $added = $original->add(new Money(500, 'USD'));

        // Original should not be modified
        $this->assertEquals(1000, $original->getAmountInCents());
        $this->assertEquals(1500, $added->getAmountInCents());
    }
}
