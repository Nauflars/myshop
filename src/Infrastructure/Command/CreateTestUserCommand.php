<?php

namespace App\Infrastructure\Command;

use App\Domain\Entity\User;
use App\Domain\ValueObject\Email;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-test-user',
    description: 'Create a test user quickly',
)]
class CreateTestUserCommand extends Command
{
    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->passwordHasher = $passwordHasher;
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'User email')
            ->addArgument('name', InputArgument::OPTIONAL, 'User name', 'Test User')
            ->addArgument('password', InputArgument::OPTIONAL, 'User password', 'test123');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $email = $input->getArgument('email');
        $name = $input->getArgument('name');
        $password = $input->getArgument('password');

        // Check if user exists
        $userRepo = $this->entityManager->getRepository(User::class);
        $existingUser = $userRepo->findOneBy(['email' => $email]);

        if ($existingUser) {
            $io->warning("User already exists: $email");
            $io->writeln("User ID: " . $existingUser->getId());
            return Command::SUCCESS;
        }

        // Create new user
        $emailVO = new Email($email);
        
        // Hash password first
        // We need a temporary user to hash the password, so we'll hash it with a dummy object
        $tempUser = new class($emailVO) implements \Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface {
            private $email;
            public function __construct($email) { $this->email = $email; }
            public function getPassword(): ?string { return null; }
            public function getUserIdentifier(): string { return (string)$this->email; }
        };
        $hashedPassword = $this->passwordHasher->hashPassword($tempUser, $password);
        
        // Now create the real user with the hashed password
        $user = new User($name, $emailVO, $hashedPassword, 'ROLE_CUSTOMER');

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success("✓ User created successfully!");
        $io->writeln("Email: $email");
        $io->writeln("Name: $name");
        $io->writeln("Password: $password");
        $io->writeln("User ID: " . $user->getId());

        return Command::SUCCESS;
    }
}
