<?php

namespace App\Domain\ValueObject;

final readonly class Money
{
    private int $amountInCents;
    private string $currency;

    public function __construct(int $amountInCents, string $currency = 'USD')
    {
        if ($amountInCents < 0) {
            throw new \InvalidArgumentException('Amount cannot be negative');
        }

        if (3 !== strlen($currency)) {
            throw new \InvalidArgumentException('Currency must be a 3-letter ISO code');
        }

        $this->amountInCents = $amountInCents;
        $this->currency = strtoupper($currency);
    }

    public static function fromDecimal(float $amount, string $currency = 'USD'): self
    {
        return new self((int) round($amount * 100), $currency);
    }

    public function getAmountInCents(): int
    {
        return $this->amountInCents;
    }

    public function getAmountAsDecimal(): float
    {
        return $this->amountInCents / 100;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function format(): string
    {
        $symbols = [
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
        ];

        $symbol = $symbols[$this->currency] ?? $this->currency;

        return sprintf('%s%.2f', $symbol, $this->getAmountAsDecimal());
    }

    public function add(Money $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amountInCents + $other->amountInCents, $this->currency);
    }

    public function subtract(Money $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amountInCents - $other->amountInCents, $this->currency);
    }

    public function multiply(int $multiplier): self
    {
        return new self($this->amountInCents * $multiplier, $this->currency);
    }

    public function equals(Money $other): bool
    {
        return $this->amountInCents === $other->amountInCents
            && $this->currency === $other->currency;
    }

    private function assertSameCurrency(Money $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new \InvalidArgumentException('Cannot operate on different currencies');
        }
    }

    public function __toString(): string
    {
        return $this->format();
    }
}
