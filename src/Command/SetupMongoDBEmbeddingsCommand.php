<?php

declare(strict_types=1);

namespace App\Command;

use MongoDB\Client;
use MongoDB\Driver\Exception\Exception as MongoException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * SetupMongoDBEmbeddingsCommand - Initialize MongoDB for User Embeddings Queue.
 *
 * Implements spec-014 T010: MongoDB collection setup with schema validation
 * Creates user_embeddings collection with validation rules and indexes
 */
#[AsCommand(
    name: 'app:setup-mongodb-embeddings',
    description: 'Setup MongoDB user_embeddings collection with validation schema and indexes'
)]
class SetupMongoDBEmbeddingsCommand extends Command
{
    public function __construct(
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
                The <info>%command.name%</info> command initializes MongoDB for the user embeddings queue system.
                
                This command will:
                  1. Create the user_embeddings collection (if not exists)
                  2. Apply JSON schema validation rules
                  3. Create indexes for efficient queries
                  4. Verify setup completion
                
                Usage:
                  <info>php %command.full_name%</info>
                
                The user_embeddings collection stores 1536-dimensional vectors
                representing user interests, updated asynchronously by RabbitMQ workers.
                HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('MongoDB User Embeddings Collection Setup');
        $io->info(sprintf('Database: %s', $this->databaseName));

        try {
            $database = $this->mongoClient->selectDatabase($this->databaseName);
            $collectionName = 'user_embeddings';

            // Check if collection already exists
            $existingCollections = iterator_to_array($database->listCollections(['name' => $collectionName]));

            if (count($existingCollections) > 0) {
                $io->info(sprintf('Collection "%s" already exists. Updating validation rules...', $collectionName));

                // Update validation rules
                $database->command([
                    'collMod' => $collectionName,
                    'validator' => $this->getValidationSchema(),
                    'validationLevel' => 'moderate',
                    'validationAction' => 'error',
                ]);
            } else {
                $io->section('Creating user_embeddings collection...');

                // Create collection with validation
                $database->createCollection($collectionName, [
                    'validator' => $this->getValidationSchema(),
                    'validationLevel' => 'moderate',
                    'validationAction' => 'error',
                ]);

                $io->success('Collection created successfully!');
            }

            // Create indexes
            $io->section('Creating indexes...');
            $collection = $database->selectCollection($collectionName);

            $indexes = [
                [
                    'name' => 'idx_user_id',
                    'key' => ['user_id' => 1],
                    'unique' => true,
                ],
                [
                    'name' => 'idx_last_updated',
                    'key' => ['last_updated' => -1],
                ],
                [
                    'name' => 'idx_event_count',
                    'key' => ['event_count' => -1],
                ],
            ];

            foreach ($indexes as $indexSpec) {
                try {
                    $collection->createIndex(
                        $indexSpec['key'],
                        [
                            'name' => $indexSpec['name'],
                            'unique' => $indexSpec['unique'] ?? false,
                        ]
                    );
                    $io->writeln(sprintf('  ✓ Created index: %s', $indexSpec['name']));
                } catch (MongoException $e) {
                    if (str_contains($e->getMessage(), 'already exists')) {
                        $io->writeln(sprintf('  - Index already exists: %s', $indexSpec['name']));
                    } else {
                        throw $e;
                    }
                }
            }

            // Verify setup
            $io->section('Verifying setup...');

            $indexes = iterator_to_array($collection->listIndexes());
            $io->writeln(sprintf('Total indexes: %d', count($indexes)));

            foreach ($indexes as $index) {
                $io->writeln(sprintf('  - %s', $index->getName()));
            }

            $io->newLine();
            $io->success([
                'MongoDB setup completed successfully!',
                sprintf('Database: %s', $this->databaseName),
                sprintf('Collection: %s', $collectionName),
                'Schema validation: ACTIVE',
                sprintf('Indexes: %d created', count($indexes)),
            ]);

            $this->logger->info('MongoDB user embeddings collection setup completed', [
                'database' => $this->databaseName,
                'collection' => $collectionName,
                'indexes_count' => count($indexes),
            ]);

            return Command::SUCCESS;
        } catch (MongoException $e) {
            $io->error([
                'MongoDB setup failed!',
                $e->getMessage(),
            ]);

            $this->logger->error('MongoDB setup failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }
    }

    private function getValidationSchema(): array
    {
        return [
            '$jsonSchema' => [
                'bsonType' => 'object',
                'required' => ['user_id', 'embedding', 'dimension_count', 'last_updated'],
                'properties' => [
                    'user_id' => [
                        'bsonType' => 'int',
                        'description' => 'User ID (references MySQL users table)',
                    ],
                    'embedding' => [
                        'bsonType' => 'array',
                        'minItems' => 1536,
                        'maxItems' => 1536,
                        'items' => [
                            'bsonType' => 'double',
                        ],
                        'description' => '1536-dimensional embedding vector (OpenAI text-embedding-3-small)',
                    ],
                    'dimension_count' => [
                        'bsonType' => 'int',
                        'minimum' => 1536,
                        'maximum' => 1536,
                        'description' => 'Embedding dimension count (must be 1536)',
                    ],
                    'last_updated' => [
                        'bsonType' => 'date',
                        'description' => 'Timestamp of last embedding update',
                    ],
                    'event_count' => [
                        'bsonType' => 'int',
                        'minimum' => 0,
                        'description' => 'Total number of events processed for this user',
                    ],
                    'last_event_type' => [
                        'bsonType' => 'string',
                        'enum' => ['search', 'product_view', 'product_click', 'product_purchase'],
                        'description' => 'Type of the most recent event processed',
                    ],
                    'created_at' => [
                        'bsonType' => 'date',
                        'description' => 'Timestamp when embedding was first created',
                    ],
                    'updated_at' => [
                        'bsonType' => 'date',
                        'description' => 'Timestamp when document was last modified',
                    ],
                    'version' => [
                        'bsonType' => 'int',
                        'minimum' => 0,
                        'description' => 'Optimistic locking version number',
                    ],
                ],
            ],
        ];
    }
}
