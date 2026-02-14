<?php

namespace App\Application\UseCase;

use App\Domain\Entity\User;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\ValueObject\Email;
use Psr\Log\LoggerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class CreateUser
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function execute(string $name, string $email, string $plainPassword, string $role = 'ROLE_CUSTOMER'): User
    {
        $emailVO = new Email($email);

        // Check if user already exists
        $existingUser = $this->userRepository->findByEmail($email);
        if (null !== $existingUser) {
            throw new \InvalidArgumentException('User with this email already exists');
        }

        // Create user with temporary password
        $user = new User($name, $emailVO, 'temp', $role);

        // Hash the password
        $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
        $user->setPasswordHash($hashedPassword);

        $this->userRepository->save($user);

        $this->logger->info('User created successfully', [
            'userId' => $user->getId(),
            'email' => $user->getEmail(),
        ]);

        return $user;
    }
}
