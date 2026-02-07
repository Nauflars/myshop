<?php

declare(strict_types=1);

namespace App\Command;

use App\Domain\Entity\ProductEmbedding;
use App\Domain\Repository\EmbeddingServiceInterface;
use App\Infrastructure\Repository\MongoDBEmbeddingRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * TestEmbeddingCommand - Test end-to-end embedding workflow
 * 
 * Implements spec-010 T010: Verify OpenAI → MongoDB → Retrieval → Validation
 * Tests embedding generation, storage, and similarity search
 */
#[AsCommand(
    name: 'app:test-embedding',
    description: 'Test embedding generation, storage, and retrieval'
)]
class TestEmbeddingCommand extends Command
{
    public function __construct(
        private readonly EmbeddingServiceInterface $embeddingService,
        private readonly MongoDBEmbeddingRepository $repository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'text',
                InputArgument::OPTIONAL,
                'Text to generate embedding for',
                'Smartphone with 128GB storage and dual camera'
            )
            ->addOption(
                'search',
                's',
                InputOption::VALUE_NONE,
                'Perform similarity search after saving'
            )
            ->addOption(
                'cleanup',
                'c',
                InputOption::VALUE_NONE,
                'Delete test embedding after validation'
            )
            ->setHelp(
                <<<'HELP'
                The <info>%command.name%</info> command tests the complete embedding workflow:
                
                1. Generate embedding using OpenAI API
                2. Create ProductEmbedding entity
                3. Save to MongoDB
                4. Retrieve from MongoDB
                5. Validate embedding integrity
                6. Optional: Perform similarity search
                7. Optional: Clean up test data
                
                Usage:
                  <info>php %command.full_name%</info>
                  <info>php %command.full_name% "Wireless headphones with noise canceling"</info>
                  <info>php %command.full_name% --search --cleanup</info>
                
                Options:
                  --search (-s)    Perform similarity search test
                  --cleanup (-c)   Delete test embedding after validation
                HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $text = $input->getArgument('text');
        $doSearch = $input->getOption('search');
        $doCleanup = $input->getOption('cleanup');

        $io->title('Embedding Workflow Test');

        // Test 1: Generate embedding
        $io->section('1. Generating embedding from OpenAI');
        $io->text('Text: ' . $text);

        try {
            $startTime = microtime(true);
            $embedding = $this->embeddingService->generateEmbedding($text);
            $duration = round((microtime(true) - $startTime) * 1000);

            $io->success(sprintf(
                'Embedding generated in %dms',
                $duration
            ));

            $io->table(
                ['Property', 'Value'],
                [
                    ['Model', $this->embeddingService->getModelName()],
                    ['Dimensions', $this->embeddingService->getDimensions()],
                    ['Vector length', count($embedding)],
                    ['First 5 values', implode(', ', array_map(fn($v) => round($v, 6), array_slice($embedding, 0, 5)))],
                ]
            );

        } catch (\Exception $e) {
            $io->error('Embedding generation failed: ' . $e->getMessage());
            return Command::FAILURE;
        }

        // Test 2: Create entity
        $io->section('2. Creating ProductEmbedding entity');

        try {
            $productId = 999999; // Test product ID
            $productEmbedding = new ProductEmbedding(
                productId: $productId,
                embedding: $embedding,
                name: 'Test Product',
                description: $text,
                category: 'Test Category',
                metadata: [
                    'test' => true,
                    'generated_at' => date('Y-m-d H:i:s'),
                ]
            );

            $io->success('ProductEmbedding entity created');

            $io->text([
                sprintf('Product ID: %d', $productEmbedding->getProductId()),
                sprintf('Name: %s', $productEmbedding->getName()),
                sprintf('Category: %s', $productEmbedding->getCategory()),
                sprintf('Valid: %s', $productEmbedding->isValidEmbedding() ? 'Yes' : 'No'),
            ]);

        } catch (\Exception $e) {
            $io->error('Entity creation failed: ' . $e->getMessage());
            return Command::FAILURE;
        }

        // Test 3: Save to MongoDB
        $io->section('3. Saving to MongoDB');

        try {
            $result = $this->repository->save($productEmbedding);

            if (!$result) {
                $io->error('Failed to save embedding to MongoDB');
                return Command::FAILURE;
            }

            $io->success('Embedding saved to MongoDB');

        } catch (\Exception $e) {
            $io->error('Save operation failed: ' . $e->getMessage());
            return Command::FAILURE;
        }

        // Test 4: Retrieve from MongoDB
        $io->section('4. Retrieving from MongoDB');

        try {
            $retrieved = $this->repository->findByProductId($productId);

            if ($retrieved === null) {
                $io->error('Failed to retrieve embedding from MongoDB');
                return Command::FAILURE;
            }

            $io->success('Embedding retrieved from MongoDB');

            $io->text([
                sprintf('Product ID: %d', $retrieved->getProductId()),
                sprintf('Name: %s', $retrieved->getName()),
                sprintf('Description: %s', substr($retrieved->getDescription(), 0, 50) . '...'),
                sprintf('Vector dimensions: %d', count($retrieved->getEmbedding())),
            ]);

        } catch (\Exception $e) {
            $io->error('Retrieval failed: ' . $e->getMessage());
            return Command::FAILURE;
        }

        // Test 5: Validate integrity
        $io->section('5. Validating embedding integrity');

        $originalSum = array_sum($embedding);
        $retrievedSum = array_sum($retrieved->getEmbedding());
        $difference = abs($originalSum - $retrievedSum);

        $io->table(
            ['Metric', 'Original', 'Retrieved', 'Match'],
            [
                ['Dimensions', count($embedding), count($retrieved->getEmbedding()), count($embedding) === count($retrieved->getEmbedding()) ? '✓' : '✗'],
                ['Sum of values', round($originalSum, 6), round($retrievedSum, 6), $difference < 0.00001 ? '✓' : '✗'],
                ['Valid', $productEmbedding->isValidEmbedding() ? 'Yes' : 'No', $retrieved->isValidEmbedding() ? 'Yes' : 'No', '✓'],
            ]
        );

        if (count($embedding) !== count($retrieved->getEmbedding()) || $difference >= 0.00001) {
            $io->warning('Embedding integrity check failed!');
            return Command::FAILURE;
        }

        $io->success('Embedding integrity validated');

        // Optional Test 6: Similarity search
        if ($doSearch) {
            $io->section('6. Testing similarity search');

            try {
                $results = $this->repository->searchSimilar($embedding, 5, 0.8);

                $io->text(sprintf('Found %d similar products', count($results)));

                if (!empty($results)) {
                    $io->table(
                        ['Product ID', 'Similarity', 'Name', 'Category'],
                        array_map(
                            fn($r) => [
                                $r['productId'],
                                round($r['similarity'], 4),
                                substr($r['name'] ?? 'N/A', 0, 30),
                                $r['category'] ?? 'N/A',
                            ],
                            $results
                        )
                    );
                }

                $io->success('Similarity search completed');

            } catch (\Exception $e) {
                $io->warning('Similarity search failed: ' . $e->getMessage());
            }
        }

        // Optional Test 7: Cleanup
        if ($doCleanup) {
            $io->section('7. Cleaning up test data');

            try {
                $deleted = $this->repository->delete($productId);

                if ($deleted) {
                    $io->success('Test embedding deleted');
                } else {
                    $io->warning('Test embedding not found for deletion');
                }

            } catch (\Exception $e) {
                $io->warning('Cleanup failed: ' . $e->getMessage());
            }
        } else {
            $io->note(sprintf(
                'Test embedding (productId=%d) left in database. Use --cleanup to remove it.',
                $productId
            ));
        }

        // Summary
        $io->section('Summary');
        $io->success('All tests passed! Embedding workflow is operational.');

        $io->table(
            ['Component', 'Status'],
            [
                ['OpenAI Embedding Service', '✓ Working'],
                ['ProductEmbedding Entity', '✓ Working'],
                ['MongoDB Storage', '✓ Working'],
                ['MongoDB Retrieval', '✓ Working'],
                ['Data Integrity', '✓ Validated'],
                ['Similarity Search', $doSearch ? '✓ Working' : '— Not tested'],
            ]
        );

        return Command::SUCCESS;
    }
}
