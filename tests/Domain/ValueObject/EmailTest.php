<?php

namespace App\Tests\Domain\ValueObject;

use App\Domain\ValueObject\Email;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class EmailTest extends TestCase
{
    public function testEmailCreation(): void
    {
        $email = new Email('test@example.com');
        
        $this->assertEquals('test@example.com', $email->getValue());
        $this->assertEquals('test@example.com', (string) $email);
    }

    public function testEmailIsLowercased(): void
    {
        $email = new Email('TEST@EXAMPLE.COM');
        
        $this->assertEquals('test@example.com', $email->getValue());
    }

    public function testEmailIsTrimmed(): void
    {
        $email = new Email('  test@example.com  ');
        
        $this->assertEquals('test@example.com', $email->getValue());
    }

    public function testInvalidEmailThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('"invalid-email" is not a valid email address');
        new Email('invalid-email');
    }

    /**
     * @dataProvider invalidEmailProvider
     */
    public function testVariousInvalidEmailsThrowException(string $invalidEmail): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Email($invalidEmail);
    }

    public static function invalidEmailProvider(): array
    {
        return [
            [''],
            ['@example.com'],
            ['test@'],
            ['test'],
            ['test@@example.com'],
            ['test @example.com'],
            ['test@example'],
        ];
    }

    /**
     * @dataProvider validEmailProvider
     */
    public function testVariousValidEmails(string $validEmail): void
    {
        $email = new Email($validEmail);
        $this->assertInstanceOf(Email::class, $email);
    }

    public static function validEmailProvider(): array
    {
        return [
            ['user@example.com'],
            ['user.name@example.com'],
            ['user+tag@example.com'],
            ['user_name@example.co.uk'],
            ['123@example.com'],
        ];
    }

    public function testEquals(): void
    {
        $email1 = new Email('test@example.com');
        $email2 = new Email('test@example.com');
        $email3 = new Email('other@example.com');
        
        $this->assertTrue($email1->equals($email2));
        $this->assertFalse($email1->equals($email3));
    }

    public function testEqualsCaseInsensitive(): void
    {
        $email1 = new Email('Test@Example.com');
        $email2 = new Email('test@example.com');
        
        $this->assertTrue($email1->equals($email2));
    }

    public function testToString(): void
    {
        $email = new Email('test@example.com');
        
        $this->assertEquals('test@example.com', (string) $email);
    }
}
