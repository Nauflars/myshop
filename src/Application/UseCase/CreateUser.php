<?php

namespace App\Application\UseCase;

use App\Application\Service\UserProfileUpdateService;
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
        private readonly UserProfileUpdateService $profileUpdateService,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(string $name, string $email, string $plainPassword, string $role = 'ROLE_CUSTOMER'): User
    {
        $emailVO = new Email($email);

        // Check if user already exists
        $existingUser = $this->userRepository->findByEmail($email);
        if ($existingUser !== null) {
            throw new \InvalidArgumentException('User with this email already exists');
        }

        // Create user with temporary password
        $user = new User($name, $emailVO, 'temp', $role);

        // Hash the password
        $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
        $user->setPasswordHash($hashedPassword);

        $this->userRepository->save($user);

        // Create initial user profile automatically
        try {
            $this->profileUpdateService->scheduleProfileUpdate($user);
            $this->logger->info('Initial profile created for new user', [
                'userId' => $user->getId(),
                'email' => $user->getEmail(),
            ]);
        } catch (\Exception $e) {
            // Log but don't fail registration if profile creation fails
            $this->logger->error('Failed to create initial profile for new user', [
                'userId' => $user->getId(),
                'error' => $e->getMessage(),
            ]);
        }

        return $user;
    }
}
