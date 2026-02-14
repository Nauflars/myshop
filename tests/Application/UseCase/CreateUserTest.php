<?php

namespace App\Tests\Application\UseCase;

use App\Application\UseCase\CreateUser;
use App\Domain\Entity\User;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\ValueObject\Email;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class CreateUserTest extends TestCase
{
    private UserRepositoryInterface $userRepository;
    private UserPasswordHasherInterface $passwordHasher;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    public function testExecuteCreatesNewUser(): void
    {
        $this->userRepository->expects($this->once())
            ->method('findByEmail')
            ->with('john@example.com')
            ->willReturn(null);

        $this->passwordHasher->expects($this->once())
            ->method('hashPassword')
            ->willReturn('hashed_password_123');

        $this->userRepository->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(User::class));

        $useCase = new CreateUser(
            $this->userRepository,
            $this->passwordHasher,
            $this->logger
        );
        $user = $useCase->execute('John Doe', 'john@example.com', 'plain_password');

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('John Doe', $user->getName());
        $this->assertEquals('john@example.com', $user->getEmail());
        $this->assertContains('ROLE_CUSTOMER', $user->getRoles());
        $this->assertContains('ROLE_USER', $user->getRoles());
    }

    public function testExecuteWithCustomRole(): void
    {
        $this->userRepository->method('findByEmail')->willReturn(null);
        $this->passwordHasher->method('hashPassword')->willReturn('hashed');
        $this->userRepository->method('save');

        $useCase = new CreateUser(
            $this->userRepository,
            $this->passwordHasher,
            $this->logger
        );
        $user = $useCase->execute('Admin', 'admin@example.com', 'pass', 'ROLE_ADMIN');

        $this->assertContains('ROLE_ADMIN', $user->getRoles());
        $this->assertContains('ROLE_USER', $user->getRoles());
    }

    public function testExecuteThrowsExceptionIfUserExists(): void
    {
        $existingUser = new User('Existing', new Email('john@example.com'), 'hash');

        $this->userRepository->expects($this->once())
            ->method('findByEmail')
            ->with('john@example.com')
            ->willReturn($existingUser);

        $useCase = new CreateUser(
            $this->userRepository,
            $this->passwordHasher,
            $this->logger
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('User with this email already exists');

        $useCase->execute('John Doe', 'john@example.com', 'password');
    }

    public function testExecuteHashesPassword(): void
    {
        $this->userRepository->method('findByEmail')->willReturn(null);

        $this->passwordHasher->expects($this->once())
            ->method('hashPassword')
            ->with(
                $this->isInstanceOf(User::class),
                'my_plain_password'
            )
            ->willReturn('secure_hashed_password');

        $this->userRepository->method('save');

        $useCase = new CreateUser(
            $this->userRepository,
            $this->passwordHasher,
            $this->logger
        );
        $user = $useCase->execute('Test', 'test@example.com', 'my_plain_password');

        $this->assertEquals('secure_hashed_password', $user->getPasswordHash());
    }
}
