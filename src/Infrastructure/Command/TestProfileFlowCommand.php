<?php

declare(strict_types=1);

namespace App\Infrastructure\Command;

use App\Application\Service\ProfileAggregationService;
use App\Application\Service\RecommendationService;
use App\Application\Service\UserProfileService;
use App\Domain\Repository\UserProfileRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-profile-flow',
    description: 'Test complete profile creation and recommendation flow'
)]
class TestProfileFlowCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ProfileAggregationService $profileAggregation,
        private readonly UserProfileService $userProfileService,
        private readonly UserProfileRepositoryInterface $profileRepository,
        private readonly RecommendationService $recommendationService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED, 'User email');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = $input->getArgument('email');

        $io->title('Testing Profile Creation Flow');
        $io->writeln("User: {$email}\n");

        // Step 1: Get user
        $io->section('Step 1: Finding user in database');
        $userRepo = $this->entityManager->getRepository('App\Domain\Entity\User');
        $user = $userRepo->findOneBy(['email' => $email]);

        if (!$user) {
            $io->error("User not found: {$email}");

            return Command::FAILURE;
        }

        $io->success("✓ User found: {$user->getName()} ({$user->getId()})");

        // Step 2: Check existing profile
        $io->section('Step 2: Checking existing profile in MongoDB');
        $existingProfile = $this->profileRepository->findByUserId((string) $user->getId());

        if ($existingProfile) {
            $io->warning('Profile already exists!');
            $io->writeln('  - Embedding length: '.count($existingProfile->getEmbeddingVector()));
            $io->writeln('  - Purchases: '.count($existingProfile->getDataSnapshot()->getRecentPurchases()));
            $io->writeln('  - Searches: '.count($existingProfile->getDataSnapshot()->getRecentSearches()));
            $lastActivity = $existingProfile->getLastActivityDate();
            $io->writeln('  - Last updated: '.($lastActivity ? $lastActivity->format('Y-m-d H:i:s') : 'N/A'));
        } else {
            $io->writeln('No existing profile found');
        }

        // Step 3: Aggregate data
        $io->section('Step 3: Aggregating user data');
        try {
            $snapshot = $this->profileAggregation->aggregateUserData($user);

            $purchases = $snapshot->getRecentPurchases();
            $searches = $snapshot->getRecentSearches();
            $categories = $snapshot->getDominantCategories();

            $io->writeln('Purchases found: '.count($purchases));
            if (count($purchases) > 0) {
                $io->listing(array_slice($purchases, 0, 5));
            }

            $io->writeln("\nSearches found: ".count($searches));
            if (count($searches) > 0) {
                $io->listing(array_slice($searches, 0, 5));
            }

            $io->writeln("\nCategories found: ".count($categories));
            if (count($categories) > 0) {
                foreach ($categories as $cat) {
                    $io->writeln("  - {$cat['name']} (weight: {$cat['weight']})");
                }
            }

            $hasActivity = count($purchases) > 0 || count($searches) > 0 || count($categories) > 0;

            if (!$hasActivity) {
                $io->warning('User has NO activity!');
                $io->writeln('Creating basic profile with user name...');

                // Create basic snapshot
                $userName = $user->getName() ?? '';
                $initialData = !empty($userName) ? [$userName, 'new user'] : ['new user'];
                $snapshot = new \App\Domain\ValueObject\ProfileSnapshot(
                    recentPurchases: $initialData,
                    recentSearches: [],
                    dominantCategories: []
                );
            }
        } catch (\Exception $e) {
            $io->error('Failed to aggregate data: '.$e->getMessage());

            return Command::FAILURE;
        }

        // Step 4: Generate profile
        $io->section('Step 4: Generating/Updating profile with OpenAI embedding');
        try {
            $profile = $this->userProfileService->refreshProfile($user);
            if ($profile) {
                $io->success('✓ Profile generated/updated successfully!');
            } else {
                $io->warning('Profile generation returned null (no meaningful activity data)');
            }
        } catch (\Exception $e) {
            $io->error('Failed to generate profile: '.$e->getMessage());
            $io->writeln('Error trace:');
            $io->writeln($e->getTraceAsString());

            return Command::FAILURE;
        }

        // Step 5: Verify profile in MongoDB
        $io->section('Step 5: Verifying profile in MongoDB');
        $newProfile = $this->profileRepository->findByUserId((string) $user->getId());

        if ($newProfile) {
            $io->success('✓ Profile found in MongoDB!');
            $io->writeln('Details:');
            $io->writeln('  - User ID: '.$newProfile->getUserId());
            $io->writeln('  - Embedding dimensions: '.count($newProfile->getEmbeddingVector()));
            $io->writeln('  - Embedding sample (first 5): '.json_encode(array_slice($newProfile->getEmbeddingVector(), 0, 5)));
            $io->writeln('  - Purchases: '.count($newProfile->getDataSnapshot()->getRecentPurchases()));
            $io->writeln('  - Searches: '.count($newProfile->getDataSnapshot()->getRecentSearches()));
            $lastActivity = $newProfile->getLastActivityDate();
            $io->writeln('  - Last updated: '.($lastActivity ? $lastActivity->format('Y-m-d H:i:s') : 'N/A'));
        } else {
            $io->error('Profile NOT found in MongoDB after generation!');

            return Command::FAILURE;
        }

        // Step 6: Test recommendations
        $io->section('Step 6: Testing recommendations');
        try {
            $recommendations = $this->recommendationService->getRecommendationsForUser($user, 10);

            $io->writeln('Recommendations count: '.$recommendations->count());
            $io->writeln('Is empty: '.($recommendations->isEmpty() ? 'yes' : 'no'));
            $io->writeln('Average score: '.$recommendations->getAverageScore());

            if (!$recommendations->isEmpty()) {
                $io->success('✓ Recommendations generated!');
                $io->table(
                    ['Product', 'Score', 'Stock'],
                    array_map(function ($product, $index) use ($recommendations) {
                        $score = $recommendations->getScores()[$index] ?? 0;

                        return [
                            $product->getName(),
                            number_format($score, 4),
                            $product->getStock() > 0 ? '✓' : '✗',
                        ];
                    }, $recommendations->getProducts(), array_keys($recommendations->getProducts()))
                );
            } else {
                $io->warning('No recommendations generated');
            }
        } catch (\Exception $e) {
            $io->error('Failed to get recommendations: '.$e->getMessage());
            $io->writeln($e->getTraceAsString());
        }

        // Summary
        $io->section('Summary');
        $io->success('✓ Profile flow test complete!');
        $io->writeln([
            '1. User found: ✓',
            '2. Data aggregated: ✓',
            '3. Profile generated: ✓',
            '4. Profile in MongoDB: ✓',
            '5. Recommendations: '.($recommendations->isEmpty() ? '✗' : '✓'),
        ]);

        return Command::SUCCESS;
    }
}
