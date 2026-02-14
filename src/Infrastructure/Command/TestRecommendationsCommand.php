<?php

namespace App\Infrastructure\Command;

use App\Application\Service\RecommendationService;
use App\Domain\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-recommendations',
    description: 'Test recommendations for a user'
)]
class TestRecommendationsCommand extends Command
{
    private EntityManagerInterface $entityManager;
    private RecommendationService $recommendationService;

    public function __construct(
        EntityManagerInterface $entityManager,
        RecommendationService $recommendationService,
    ) {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->recommendationService = $recommendationService;
    }

    protected function configure(): void
    {
        $this->addOption(
            'user-id',
            'u',
            InputOption::VALUE_REQUIRED,
            'User ID to test'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $userId = $input->getOption('user-id');

        if (!$userId) {
            $io->error('Please provide --user-id');

            return Command::INVALID;
        }

        $user = $this->entityManager->getRepository(User::class)->find($userId);

        if (!$user) {
            $io->error("User not found: {$userId}");

            return Command::FAILURE;
        }

        $io->title("Testing recommendations for: {$user->getName()} ({$user->getEmail()})");

        try {
            $io->writeln('Step 1: Getting recommendations from service...');
            $recommendations = $this->recommendationService->getRecommendationsForUser($user, 12);

            $io->writeln('Step 2: Checking result object...');
            $io->writeln('  - Result class: '.get_class($recommendations));
            $io->writeln('  - Count: '.$recommendations->count());
            $io->writeln('  - Is empty: '.($recommendations->isEmpty() ? 'yes' : 'no'));
            $io->writeln('  - Average score: '.$recommendations->getAverageScore());
            $io->newLine();

            $io->writeln('Step 3: Checking products...');
            $products = $recommendations->getProducts();
            $io->writeln('  - Products array count: '.count($products));

            if (count($products) > 0) {
                $io->writeln('  - First product class: '.get_class($products[0]));
                $io->writeln('  - First product name: '.$products[0]->getName());
            }
            $io->newLine();

            $io->section('Recommendation Results');
            $io->writeln('Total recommendations: '.$recommendations->count());
            $io->writeln('Average similarity score: '.round($recommendations->getAverageScore(), 4));
            $io->newLine();

            if ($recommendations->count() > 0) {
                $io->section('Top Recommendations');
                $table = [];
                foreach ($recommendations->getProductsWithScores() as $item) {
                    $product = $item['product'];
                    $score = $item['score'];
                    $table[] = [
                        $product->getName(),
                        $product->getCategory(),
                        number_format($score, 4),
                        $product->getStock() > 0 ? 'In Stock' : 'Out of Stock',
                    ];
                }
                $io->table(['Product', 'Category', 'Score', 'Stock'], $table);
            } else {
                $io->warning('No recommendations generated');
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Failed to get recommendations: '.$e->getMessage());
            $io->writeln('Stack trace:');
            $io->writeln($e->getTraceAsString());

            return Command::FAILURE;
        }
    }
}
