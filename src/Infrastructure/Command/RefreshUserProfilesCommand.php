<?php

namespace App\Infrastructure\Command;

use App\Application\Service\UserProfileService;
use App\Domain\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:user-profile:refresh',
    description: 'Refresh user profiles by regenerating embeddings from current activity data'
)]
class RefreshUserProfilesCommand extends Command
{
    private EntityManagerInterface $entityManager;
    private UserProfileService $profileService;
    private LoggerInterface $logger;

    public function __construct(
        EntityManagerInterface $entityManager,
        UserProfileService $profileService,
        LoggerInterface $logger,
    ) {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->profileService = $profileService;
        $this->logger = $logger;
    }

    protected function configure(): void
    {
        $this->addOption(
            'all',
            'a',
            InputOption::VALUE_NONE,
            'Refresh all user profiles'
        );
        $this->addOption(
            'stale-only',
            's',
            InputOption::VALUE_NONE,
            'Only refresh stale profiles (older than 30 days)'
        );
        $this->addOption(
            'user-id',
            'u',
            InputOption::VALUE_REQUIRED,
            'Refresh specific user profile by user ID'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('User Profile Refresh');

        $userId = $input->getOption('user-id');
        $all = $input->getOption('all');
        $staleOnly = $input->getOption('stale-only');

        try {
            if ($userId) {
                return $this->refreshSingleUser($io, $userId);
            } elseif ($staleOnly) {
                return $this->refreshStaleProfiles($io);
            } elseif ($all) {
                return $this->refreshAllUsers($io);
            }
            $io->error('Please specify one of: --all, --stale-only, or --user-id=<UUID>');

            return Command::INVALID;
        } catch (\Exception $e) {
            $io->error('Command failed: '.$e->getMessage());
            $this->logger->error('Profile refresh command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }
    }

    private function refreshSingleUser(SymfonyStyle $io, string $userId): int
    {
        $io->section("Refreshing profile for user: {$userId}");

        $user = $this->entityManager->getRepository(User::class)->find($userId);

        if (!$user) {
            $io->error("User not found: {$userId}");

            return Command::FAILURE;
        }

        $io->text("User: {$user->getName()} ({$user->getEmail()})");

        $profile = $this->profileService->refreshProfile($user);

        if ($profile) {
            $io->success('✓ Profile refreshed successfully');
            $io->table(
                ['Metric', 'Value'],
                [
                    ['User ID', $profile->getUserId()],
                    ['Purchases', count($profile->getDataSnapshot()->getRecentPurchases())],
                    ['Searches', count($profile->getDataSnapshot()->getRecentSearches())],
                    ['Categories', implode(', ', $profile->getDataSnapshot()->getDominantCategories())],
                    ['Last Updated', $profile->getUpdatedAt()->format('Y-m-d H:i:s')],
                ]
            );

            return Command::SUCCESS;
        }
        $io->warning('Profile not generated (user may have no activity)');

        return Command::SUCCESS;
    }

    private function refreshAllUsers(SymfonyStyle $io): int
    {
        $io->section('Refreshing all user profiles');

        $users = $this->entityManager->getRepository(User::class)->findAll();
        $total = count($users);

        $io->text("Found {$total} users");

        $io->progressStart($total);

        $success = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($users as $user) {
            try {
                $profile = $this->profileService->refreshProfile($user);

                if ($profile) {
                    ++$success;
                } else {
                    ++$skipped;
                }
            } catch (\Exception $e) {
                ++$failed;
                $this->logger->error('Failed to refresh profile', [
                    'userId' => $user->getId(),
                    'error' => $e->getMessage(),
                ]);
            }

            $io->progressAdvance();
        }

        $io->progressFinish();

        $io->success('Profile refresh completed');
        $io->table(
            ['Status', 'Count'],
            [
                ['✓ Success', $success],
                ['⊘ Skipped (no activity)', $skipped],
                ['✗ Failed', $failed],
                ['Total', $total],
            ]
        );

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    private function refreshStaleProfiles(SymfonyStyle $io): int
    {
        $io->section('Refreshing stale profiles (>30 days old)');

        $staleProfiles = $this->profileService->getStaleProfiles(30);
        $total = count($staleProfiles);

        if (0 === $total) {
            $io->success('No stale profiles found');

            return Command::SUCCESS;
        }

        $io->text("Found {$total} stale profiles");

        $io->progressStart($total);

        $success = 0;
        $failed = 0;

        foreach ($staleProfiles as $profile) {
            try {
                $user = $this->entityManager->getRepository(User::class)->find($profile->getUserId());

                if (!$user) {
                    ++$failed;
                    continue;
                }

                $refreshed = $this->profileService->refreshProfile($user);

                if ($refreshed) {
                    ++$success;
                } else {
                    ++$failed;
                }
            } catch (\Exception $e) {
                ++$failed;
                $this->logger->error('Failed to refresh stale profile', [
                    'userId' => $profile->getUserId(),
                    'error' => $e->getMessage(),
                ]);
            }

            $io->progressAdvance();
        }

        $io->progressFinish();

        $io->success('Stale profile refresh completed');
        $io->table(
            ['Status', 'Count'],
            [
                ['✓ Success', $success],
                ['✗ Failed', $failed],
                ['Total', $total],
            ]
        );

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
