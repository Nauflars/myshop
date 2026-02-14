<?php

declare(strict_types=1);

namespace App\Command;

use App\Infrastructure\Repository\MongoDBEmbeddingRepository;
use MongoDB\Client;
use MongoDB\Driver\Exception\Exception as MongoException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * CreateVectorIndexCommand - Create MongoDB vector search index.
 *
 * Implements spec-010 T009: Vector index for efficient similarity search
 * Creates compound index on productId and embedding field
 */
#[AsCommand(
    name: 'app:create-vector-index',
    description: 'Create MongoDB vector search index for product embeddings'
)]
class CreateVectorIndexCommand extends Command
{
    public function __construct(
        private readonly MongoDBEmbeddingRepository $repository,
        private readonly Client $mongoClient,
        private readonly LoggerInterface $logger,
        private readonly string $databaseName,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp(
                <<<'HELP'
                The <info>%command.name%</info> command creates a vector search index
                on the product_embeddings collection in MongoDB.
                
                This index enables efficient similarity search queries using
                the embedding field (1536-dimension vectors).
                
                Usage:
                  <info>php %command.full_name%</info>
                
                The command will:
                  1. Check if the index already exists
                  2. Create compound index: productId (unique) + embedding
                  3. Verify index creation
                
                Note: Index creation may take several minutes for large datasets.
                HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('MongoDB Vector Index Creation');

        // Get collection
        $collectionName = $this->repository->getCollectionName();
        $indexName = $this->repository->getVectorIndexName();

        $io->info(sprintf('Target: %s.%s', $this->databaseName, $collectionName));

        // Check if index already exists
        if ($this->repository->hasVectorIndex()) {
            $io->success(sprintf('Vector index "%s" already exists!', $indexName));

            return Command::SUCCESS;
        }

        $io->section('Creating vector index...');

        try {
            $collection = $this->mongoClient->selectCollection(
                $this->databaseName,
                $collectionName
            );

            // Create compound index
            // 1. productId (unique, for quick lookups)
            // 2. embedding (for vector similarity)
            $result = $collection->createIndex(
                [
                    'productId' => 1,
                    'embedding' => 1,
                ],
                [
                    'name' => $indexName,
                    'unique' => false,
                    'background' => true,
                ]
            );

            // Also create unique index on productId alone
            $collection->createIndex(
                ['productId' => 1],
                [
                    'name' => 'productId_unique',
                    'unique' => true,
                    'background' => true,
                ]
            );

            $io->success('Vector index created successfully!');
            $io->text([
                sprintf('Index name: %s', $indexName),
                'Index keys: productId (asc), embedding (asc)',
                'This index enables efficient similarity search queries.',
            ]);

            $this->logger->info('Vector index created', [
                'collection' => $collectionName,
                'index_name' => $indexName,
            ]);

            return Command::SUCCESS;
        } catch (MongoException $e) {
            $io->error('Failed to create vector index: '.$e->getMessage());

            $this->logger->error('Vector index creation failed', [
                'collection' => $collectionName,
                'error' => $e->getMessage(),
            ]);

            return Command::FAILURE;
        }
    }
}
