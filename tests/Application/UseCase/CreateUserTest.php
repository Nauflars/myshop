<?php

namespace App\Tests\Application\UseCase;

use App\Application\UseCase\CreateUser;
use App\Domain\Entity\User;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\ValueObject\Email;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class CreateUserTest extends TestCase
{
    public function testExecuteCreatesNewUser(): void
    {
        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);

        $userRepository->expects($this->once())
            ->method('findByEmail')
            ->with('john@example.com')
            ->willReturn(null);

        $passwordHasher->expects($this->once())
            ->method('hashPassword')
            ->willReturn('hashed_password_123');

        $userRepository->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(User::class));

        $useCase = new CreateUser($userRepository, $passwordHasher);
        $user = $useCase->execute('John Doe', 'john@example.com', 'plain_password');

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('John Doe', $user->getName());
        $this->assertEquals('john@example.com', $user->getEmail());
        $this->assertContains('ROLE_CUSTOMER', $user->getRoles());
        $this->assertContains('ROLE_USER', $user->getRoles());
    }

    public function testExecuteWithCustomRole(): void
    {
        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);

        $userRepository->method('findByEmail')->willReturn(null);
        $passwordHasher->method('hashPassword')->willReturn('hashed');
        $userRepository->method('save');

        $useCase = new CreateUser($userRepository, $passwordHasher);
        $user = $useCase->execute('Admin', 'admin@example.com', 'pass', 'ROLE_ADMIN');

        $this->assertContains('ROLE_ADMIN', $user->getRoles());
        $this->assertContains('ROLE_USER', $user->getRoles());
    }

    public function testExecuteThrowsExceptionIfUserExists(): void
    {
        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);

        $existingUser = new User('Existing', new Email('john@example.com'), 'hash');
        
        $userRepository->expects($this->once())
            ->method('findByEmail')
            ->with('john@example.com')
            ->willReturn($existingUser);

        $useCase = new CreateUser($userRepository, $passwordHasher);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('User with this email already exists');
        
        $useCase->execute('John Doe', 'john@example.com', 'password');
    }

    public function testExecuteHashesPassword(): void
    {
        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);

        $userRepository->method('findByEmail')->willReturn(null);
        
        $passwordHasher->expects($this->once())
            ->method('hashPassword')
            ->with(
                $this->isInstanceOf(User::class),
                'my_plain_password'
            )
            ->willReturn('secure_hashed_password');

        $userRepository->method('save');

        $useCase = new CreateUser($userRepository, $passwordHasher);
        $user = $useCase->execute('Test', 'test@example.com', 'my_plain_password');

        $this->assertEquals('secure_hashed_password', $user->getPasswordHash());
    }
}
