<?php

declare(strict_types=1);

namespace App\Command;

use MongoDB\Client;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * InitializeMongoDBCommand - Initialize MongoDB user_embeddings collection
 * 
 * Implements spec-014: Setup MongoDB collection with indexes
 */
#[AsCommand(
    name: 'app:mongodb:initialize',
    description: 'Initialize MongoDB collections and indexes for user embeddings'
)]
final class InitializeMongoDBCommand extends Command
{
    public function __construct(
        private readonly Client $mongoClient,
        private readonly LoggerInterface $logger,
        private readonly string $databaseName = 'myshop'
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'drop',
                'd',
                InputOption::VALUE_NONE,
                'Drop existing collection before creating'
            )
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Force operation without confirmation'
            )
            ->setHelp(<<<'HELP'
The <info>%command.name%</info> command initializes MongoDB collections and indexes
for the user embeddings feature.

Creates:
  - user_embeddings collection
  - Unique index on user_id
  - Index on last_updated_at for stale queries

Usage:
  <info>php %command.full_name%</info>

Options:
  <info>--drop</info>    Drop existing collection (WARNING: destroys all data)
  <info>--force</info>   Skip confirmation prompts

Examples:
  # Initialize with confirmation
  <info>php bin/console app:mongodb:initialize</info>

  # Drop and recreate (BE CAREFUL!)
  <info>php bin/console app:mongodb:initialize --drop --force</info>
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $drop = $input->getOption('drop');
        $force = $input->getOption('force');

        $io->title('MongoDB Initialization for User Embeddings');

        try {
            $database = $this->mongoClient->selectDatabase($this->databaseName);
            $collectionName = 'user_embeddings';

            // Check if collection exists
            $collections = iterator_to_array($database->listCollections([
                'filter' => ['name' => $collectionName]
            ]));
            $exists = count($collections) > 0;

            if ($exists) {
                $io->info(sprintf('Collection "%s" already exists', $collectionName));

                if ($drop) {
                    if (!$force && !$io->confirm(
                        '⚠️  This will DELETE all user embeddings. Continue?',
                        false
                    )) {
                        $io->warning('Operation cancelled');
                        return Command::SUCCESS;
                    }

                    $io->section('Dropping existing collection...');
                    $database->dropCollection($collectionName);
                    $io->success('Collection dropped');
                    $exists = false;
                }
            }

            if (!$exists) {
                $io->section('Creating user_embeddings collection');
                
                // Create collection with validation schema
                $database->createCollection($collectionName, [
                    'validator' => [
                        '$jsonSchema' => [
                            'bsonType' => 'object',
                            'required' => ['user_id', 'vector', 'last_updated_at', 'version'],
                            'properties' => [
                                'user_id' => [
                                    'bsonType' => 'int',
                                    'description' => 'User identifier (required, unique)'
                                ],
                                'vector' => [
                                    'bsonType' => 'array',
                                    'minItems' => 1536,
                                    'maxItems' => 1536,
                                    'items' => [
                                        'bsonType' => 'double',
                                        'description' => '1536-dimensional embedding vector'
                                    ]
                                ],
                                'last_updated_at' => [
                                    'bsonType' => 'string',
                                    'description' => 'ISO 8601 timestamp of last update'
                                ],
                                'version' => [
                                    'bsonType' => 'int',
                                    'minimum' => 1,
                                    'description' => 'Optimistic locking version'
                                ],
                                'created_at' => [
                                    'bsonType' => ['string', 'null'],
                                    'description' => 'ISO 8601 timestamp of creation'
                                ]
                            ]
                        ]
                    ]
                ]);

                $io->success('Collection created with validation schema');
            }

            // Create indexes
            $io->section('Creating indexes');
            $collection = $database->selectCollection($collectionName);

            // Unique index on user_id
            $collection->createIndex(
                ['user_id' => 1],
                [
                    'unique' => true,
                    'name' => 'idx_user_id',
                    'background' => false
                ]
            );
            $io->writeln('✓ Created unique index: idx_user_id');

            // Index on last_updated_at for stale queries
            $collection->createIndex(
                ['last_updated_at' => 1],
                [
                    'name' => 'idx_last_updated',
                    'background' => false
                ]
            );
            $io->writeln('✓ Created index: idx_last_updated');

            // Index on version for optimistic locking queries
            $collection->createIndex(
                ['user_id' => 1, 'version' => 1],
                [
                    'name' => 'idx_user_version',
                    'background' => false
                ]
            );
            $io->writeln('✓ Created compound index: idx_user_version');

            // Display collection stats
            $io->section('Collection Information');
            $stats = $database->command(['collStats' => $collectionName])->toArray()[0];
            
            $io->table(
                ['Property', 'Value'],
                [
                    ['Database', $this->databaseName],
                    ['Collection', $collectionName],
                    ['Document Count', $stats['count'] ?? 0],
                    ['Storage Size', $this->formatBytes($stats['storageSize'] ?? 0)],
                    ['Index Count', $stats['nindexes'] ?? 0],
                    ['Total Index Size', $this->formatBytes($stats['totalIndexSize'] ?? 0)],
                ]
            );

            // List indexes
            $indexes = $collection->listIndexes();
            $indexTable = [];
            foreach ($indexes as $index) {
                $indexTable[] = [
                    $index['name'],
                    json_encode($index['key']),
                    $index['unique'] ?? false ? 'Yes' : 'No'
                ];
            }

            $io->table(['Index Name', 'Keys', 'Unique'], $indexTable);

            $io->success('MongoDB initialization completed successfully!');

            // Log initialization
            $this->logger->info('MongoDB user_embeddings collection initialized', [
                'database' => $this->databaseName,
                'collection' => $collectionName,
                'dropped' => $drop,
                'document_count' => $stats['count'] ?? 0,
            ]);

            return Command::SUCCESS;

        } catch (\Throwable $e) {
            $io->error('MongoDB initialization failed: ' . $e->getMessage());
            
            $this->logger->error('MongoDB initialization failed', [
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);

            return Command::FAILURE;
        }
    }

    /**
     * Format bytes to human-readable size
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}
