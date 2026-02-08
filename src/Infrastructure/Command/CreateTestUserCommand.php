<?php

namespace App\Infrastructure\Command;

use App\Application\UseCase\CreateUser;
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
    private CreateUser $createUser;

    public function __construct(
        EntityManagerInterface $entityManager,
        CreateUser $createUser
    ) {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->createUser = $createUser;
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

        // Use CreateUser use case (includes automatic profile creation)
        try {
            $user = $this->createUser->execute($name, $email, $password, 'ROLE_CUSTOMER');
            
            $io->success("✓ User created successfully!");
            $io->writeln("Email: $email");
            $io->writeln("Name: $name");
            $io->writeln("Password: $password");
            $io->writeln("User ID: " . $user->getId());
            $io->writeln("");
            $io->writeln("⏳ Profile creation scheduled automatically...");
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error("Failed to create user: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
