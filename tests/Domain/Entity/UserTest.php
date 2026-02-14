<?php

namespace App\Tests\Domain\Entity;

use App\Domain\Entity\User;
use App\Domain\ValueObject\Email;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    public function testUserCreation(): void
    {
        $name = 'John Doe';
        $email = new Email('john@example.com');
        $passwordHash = 'hashed_password_123';

        $user = new User($name, $email, $passwordHash);

        $this->assertNotEmpty($user->getId());
        $this->assertEquals($name, $user->getName());
        $this->assertEquals('john@example.com', $user->getEmail());
        $this->assertEquals($passwordHash, $user->getPasswordHash());
        $this->assertContains('ROLE_CUSTOMER', $user->getRoles());
        $this->assertContains('ROLE_USER', $user->getRoles());
        $this->assertInstanceOf(\DateTimeImmutable::class, $user->getCreatedAt());
    }

    public function testUserCreationWithCustomRole(): void
    {
        $email = new Email('admin@example.com');
        $user = new User('Admin User', $email, 'pass123', 'ROLE_ADMIN');

        $this->assertContains('ROLE_ADMIN', $user->getRoles());
        $this->assertContains('ROLE_USER', $user->getRoles());
        $this->assertTrue($user->hasRole('ROLE_ADMIN'));
    }

    public function testGetUserIdentifier(): void
    {
        $email = new Email('test@example.com');
        $user = new User('Test', $email, 'pass');

        $this->assertEquals('test@example.com', $user->getUserIdentifier());
    }

    public function testGetEmailValueObject(): void
    {
        $email = new Email('test@example.com');
        $user = new User('Test', $email, 'pass');

        $emailVO = $user->getEmailValueObject();
        $this->assertInstanceOf(Email::class, $emailVO);
        $this->assertEquals('test@example.com', $emailVO->getValue());
    }

    public function testSetName(): void
    {
        $email = new Email('test@example.com');
        $user = new User('Old Name', $email, 'pass');

        $user->setName('New Name');
        $this->assertEquals('New Name', $user->getName());
    }

    public function testSetPasswordHash(): void
    {
        $email = new Email('test@example.com');
        $user = new User('Test', $email, 'old_hash');

        $user->setPasswordHash('new_hash');
        $this->assertEquals('new_hash', $user->getPasswordHash());
    }

    public function testEraseCredentials(): void
    {
        $email = new Email('test@example.com');
        $user = new User('Test', $email, 'pass');

        // Should not throw exception
        $user->eraseCredentials();
        $this->assertTrue(true);
    }

    public function testHasRole(): void
    {
        $email = new Email('test@example.com');
        $user = new User('Test', $email, 'pass', 'ROLE_CUSTOMER');

        $this->assertTrue($user->hasRole('ROLE_CUSTOMER'));
        $this->assertFalse($user->hasRole('ROLE_ADMIN'));
    }

    public function testSetRoles(): void
    {
        $email = new Email('test@example.com');
        $user = new User('Test', $email, 'pass');

        $user->setRoles(['ROLE_ADMIN', 'ROLE_SELLER']);
        $this->assertTrue($user->hasRole('ROLE_ADMIN'));
        $this->assertTrue($user->hasRole('ROLE_SELLER'));
        $this->assertTrue($user->hasRole('ROLE_USER'));
    }

    public function testIsAdmin(): void
    {
        $email = new Email('admin@example.com');
        $user = new User('Admin', $email, 'pass', 'ROLE_ADMIN');

        $this->assertTrue($user->isAdmin());
    }

    public function testIsSeller(): void
    {
        $email = new Email('seller@example.com');
        $user = new User('Seller', $email, 'pass', 'ROLE_SELLER');

        $this->assertTrue($user->isSeller());
    }
}
