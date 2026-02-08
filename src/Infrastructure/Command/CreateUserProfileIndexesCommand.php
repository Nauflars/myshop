<?php

namespace App\Infrastructure\Command;

use MongoDB\Client;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:user-profile:create-indexes',
    description: 'Create MongoDB indexes for user_profiles collection'
)]
class CreateUserProfileIndexesCommand extends Command
{
    private Client $mongoClient;
    private string $databaseName;
    private LoggerInterface $logger;

    public function __construct(
        Client $mongoClient,
        string $databaseName,
        LoggerInterface $logger
    ) {
        parent::__construct();
        $this->mongoClient = $mongoClient;
        $this->databaseName = $databaseName;
        $this->logger = $logger;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Creating MongoDB Indexes for user_profiles Collection');

        try {
            $database = $this->mongoClient->selectDatabase($this->databaseName);
            $collection = $database->selectCollection('user_profiles');

            // Create unique index on userId
            $io->section('Creating unique index on userId');
            $collection->createIndex(
                ['userId' => 1],
                ['unique' => true, 'name' => 'userId_unique']
            );
            $io->success('✓ Unique index on userId created');

            // Create index on updatedAt for stale profile queries
            $io->section('Creating index on updatedAt');
            $collection->createIndex(
                ['updatedAt' => 1],
                ['name' => 'updatedAt_1']
            );
            $io->success('✓ Index on updatedAt created');

            // Create index on lastActivityDate
            $io->section('Creating index on lastActivityDate');
            $collection->createIndex(
                ['lastActivityDate' => 1],
                ['name' => 'lastActivityDate_1']
            );
            $io->success('✓ Index on lastActivityDate created');

            // Note about vector search index
            $io->section('Vector Search Index');
            $io->note([
                'Vector search indexes must be created via MongoDB Atlas UI or API.',
                'Index name: user_profile_vector_index',
                'Index field: embeddingVector',
                'Index type: vectorSearch',
                'Dimensions: 1536',
                'Similarity: cosine',
            ]);

            $io->success('All standard indexes created successfully!');

            $this->logger->info('User profile MongoDB indexes created');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Failed to create indexes: ' . $e->getMessage());
            $this->logger->error('Failed to create user profile indexes', [
                'error' => $e->getMessage(),
            ]);
            return Command::FAILURE;
        }
    }
}
