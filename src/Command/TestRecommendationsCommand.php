<?php

namespace App\Command;

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
    description: 'Test the recommendation system for a user',
)]
class TestRecommendationsCommand extends Command
{
    public function __construct(
        private readonly RecommendationService $recommendationService,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('user-id', 'u', InputOption::VALUE_OPTIONAL, 'User UUID to test');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $userId = $input->getOption('user-id');

        $userRepo = $this->entityManager->getRepository(User::class);

        if ($userId) {
            $user = $userRepo->find($userId);
            if (!$user) {
                $io->error("User not found: $userId");

                return Command::FAILURE;
            }
        } else {
            // Get first user
            $user = $userRepo->findOneBy([]);
            if (!$user) {
                $io->error('No users in database');

                return Command::FAILURE;
            }
        }

        $io->title('Testing Recommendations');
        $io->info('User: '.$user->getEmail().' (ID: '.$user->getId().')');

        try {
            $result = $this->recommendationService->getRecommendationsForUser($user, 20);

            $io->section('Results');
            $io->writeln('Total products: '.count($result->products));
            $io->writeln('Average score: '.number_format($result->averageScore, 4));
            $io->writeln('Is personalized: '.($result->isPersonalized ? 'YES' : 'NO (fallback)'));

            if (count($result->products) > 0) {
                $io->section('Top Recommendations');

                $rows = [];
                foreach ($result->products as $i => $product) {
                    $score = $result->scores[$i] ?? 0;
                    $rows[] = [
                        $i + 1,
                        number_format($score, 4),
                        $product->getName(),
                        $product->getId(),
                    ];
                }

                $io->table(['#', 'Score', 'Product', 'ID'], $rows);

                $io->success('Recommendations are working!');

                return Command::SUCCESS;
            }
            $io->warning('No recommendations returned');

            return Command::FAILURE;
        } catch (\Exception $e) {
            $io->error('Failed to get recommendations: '.$e->getMessage());
            $io->writeln($e->getTraceAsString());

            return Command::FAILURE;
        }
    }
}
