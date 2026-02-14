<?php

namespace App\Infrastructure\Command;

use App\Application\Service\UserProfileUpdateService;
use App\Domain\Entity\Conversation;
use App\Domain\Entity\ConversationMessage;
use App\Domain\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:simulate-search',
    description: 'Simulate a search for a user to trigger automatic profile creation',
)]
class SimulateSearchCommand extends Command
{
    private EntityManagerInterface $entityManager;
    private UserProfileUpdateService $profileUpdateService;

    public function __construct(
        EntityManagerInterface $entityManager,
        UserProfileUpdateService $profileUpdateService,
    ) {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->profileUpdateService = $profileUpdateService;
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'User email')
            ->addArgument('searchQuery', InputArgument::REQUIRED, 'Search query text');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = $input->getArgument('email');
        $searchQuery = $input->getArgument('searchQuery');

        $io->title('Simulate Search & Profile Update');

        // Step 1: Find user
        $io->section('Step 1: Finding user');
        $userRepo = $this->entityManager->getRepository(User::class);
        $user = $userRepo->findOneBy(['email' => $email]);

        if (!$user) {
            $io->error("User not found: $email");

            return Command::FAILURE;
        }

        $io->success("✓ User found: {$user->getName()} ({$user->getId()})");

        // Step 2: Check existing profile in MongoDB
        $io->section('Step 2: Checking MongoDB profile (before)');
        $result = shell_exec(sprintf(
            'docker exec myshop_mongodb mongosh -u root -p rootpassword --authenticationDatabase admin myshop --quiet --eval "db.user_profiles.findOne({userId: \'%s\'})"',
            $user->getId()
        ));
        if ('null' === trim($result)) {
            $io->writeln('No profile exists');
        } else {
            $io->writeln('Profile exists');
        }

        // Step 3: Create conversation with search message
        $io->section('Step 3: Creating conversation with search message');
        $conversation = new Conversation($user);
        $this->entityManager->persist($conversation);

        $message = new ConversationMessage($conversation, 'user', $searchQuery);
        $this->entityManager->persist($message);

        $this->entityManager->flush();

        $io->success("✓ Conversation created with message: \"$searchQuery\"");
        $io->writeln('Conversation ID: '.$conversation->getId());

        // Step 4: Trigger automatic profile update
        $io->section('Step 4: Triggering automatic profile update');
        try {
            $this->profileUpdateService->scheduleProfileUpdate($user);
            $io->success('✓ Profile update scheduled/executed');
        } catch (\Exception $e) {
            $io->error('Failed to update profile: '.$e->getMessage());
            $io->writeln('Trace: '.$e->getTraceAsString());

            return Command::FAILURE;
        }

        // Step 5: Verify profile in MongoDB (after)
        $io->section('Step 5: Verifying MongoDB profile (after)');
        sleep(2); // Give it a moment to complete

        $result = shell_exec(sprintf(
            'docker exec myshop_mongodb mongosh -u root -p rootpassword --authenticationDatabase admin myshop --quiet --eval "db.user_profiles.findOne({userId: \'%s\'}, {userId: 1, lastActivityDate: 1, \'dataSnapshot.recentSearches\': 1})"',
            $user->getId()
        ));

        if ('null' === trim($result)) {
            $io->warning('⚠ Profile still does NOT exist in MongoDB');
            $io->writeln('This suggests automatic profile creation is not working.');
        } else {
            $io->success('✓ Profile EXISTS in MongoDB!');
            $io->writeln('Profile details:');
            $io->writeln($result);
        }

        return Command::SUCCESS;
    }
}
