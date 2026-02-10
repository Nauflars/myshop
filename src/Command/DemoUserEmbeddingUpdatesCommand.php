<?php

declare(strict_types=1);

namespace App\Command;

use App\Application\Message\UpdateUserEmbeddingMessage;
use App\Domain\Repository\UserEmbeddingRepositoryInterface;
use App\Domain\ValueObject\EventType;
use App\Infrastructure\Queue\RabbitMQPublisher;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Uid\Uuid;

/**
 * DemoUserEmbeddingUpdatesCommand - Demonstrate user embedding updates from events
 * 
 * Simulates real user interactions and shows how embeddings change:
 * - Semantic search
 * - Normal search  
 * - Product view
 * - Product purchase
 */
#[AsCommand(
    name: 'app:demo-user-embeddings',
    description: 'Demo: Show how user embeddings change with different interaction events'
)]
class DemoUserEmbeddingUpdatesCommand extends Command
{
    public function __construct(
        private readonly Connection $connection,
        private readonly RabbitMQPublisher $publisher,
        private readonly UserEmbeddingRepositoryInterface $embeddingRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('users', 'u', InputOption::VALUE_OPTIONAL, 'Number of users to demo (default: 3)', 3)
            ->addOption('wait', 'w', InputOption::VALUE_OPTIONAL, 'Wait time in seconds between events (default: 2)', 2)
            ->setHelp(<<<'HELP'
                This command demonstrates the user embedding queue system by:
                
                1. Selecting N users from database
                2. For each user, simulating 4 interaction types:
                   - Semantic search for products
                   - Normal product search
                   - Product view
                   - Product purchase
                3. Publishing each event to RabbitMQ queue
                4. Showing embedding changes after each event
                
                Example:
                  bin/console app:demo-user-embeddings
                  bin/console app:demo-user-embeddings --users=5 --wait=1
                HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $numUsers = (int) $input->getOption('users');
        $waitSeconds = (int) $input->getOption('wait');

        $io->title('🚀 User Embedding Updates Demo');
        $io->section("Simulating {$numUsers} users with 4 interaction types each");

        $io->warning([
            'This is a simulation demo that shows the user embedding workflow.',
            'Using simulated user IDs (1, 2, 3) and product IDs for demonstration.',
            'The actual system uses UUIDs for users and products.',
        ]);

        // Use simulated users for demo
        $users = $this->getSimulatedUsers($numUsers);
        
        $io->info(sprintf('Simulating %d users with IDs: %s', 
            count($users), 
            implode(', ', array_column($users, 'id'))
        ));
        $io->newLine();

        // Get simulated products for demo
        $products = $this->getSimulatedProducts(10);

        $totalEvents = 0;
        $successfulPublishes = 0;

        // Process each user
        foreach ($users as $userIndex => $user) {
            $userId = $user['id'];
            $userLabel = $user['label'];
            
            $io->section(sprintf('👤 User %d/%d: %s (ID: %d)', 
                $userIndex + 1, 
                count($users), 
                $userLabel, 
                $userId
            ));

            // Show initial embedding state
            $this->displayUserEmbedding($io, $userId, 'INITIAL STATE');
            $io->newLine();

            // 1. Semantic Search Event
            $io->writeln('<fg=cyan>📍 Event 1/4: Semantic Search</fg=cyan>');
            $searchPhrase = $this->getRandomSearchPhrase();
            $io->writeln("   🔍 Searching: <comment>{$searchPhrase}</comment>");
            
            $published = $this->publishEvent(
                userId: $userId,
                eventType: EventType::SEARCH,
                searchPhrase: $searchPhrase,
                metadata: ['search_type' => 'semantic']
            );
            
            if ($published) {
                $successfulPublishes++;
                $io->writeln('   ✅ Event published to queue');
            } else {
                $io->writeln('   ❌ Failed to publish event');
            }
            $totalEvents++;
            
            $this->waitAndShowProgress($io, $waitSeconds, 'Processing embedding update...');
            $this->displayUserEmbedding($io, $userId, 'AFTER SEMANTIC SEARCH');
            $io->newLine();

            // 2. Normal Search Event
            $io->writeln('<fg=cyan>📍 Event 2/4: Normal Search</fg=cyan>');
            $searchPhrase = $this->getRandomSearchPhrase();
            $io->writeln("   🔍 Searching: <comment>{$searchPhrase}</comment>");
            
            $published = $this->publishEvent(
                userId: $userId,
                eventType: EventType::SEARCH,
                searchPhrase: $searchPhrase,
                metadata: ['search_type' => 'normal']
            );
            
            if ($published) {
                $successfulPublishes++;
                $io->writeln('   ✅ Event published to queue');
            } else {
                $io->writeln('   ❌ Failed to publish event');
            }
            $totalEvents++;
            
            $this->waitAndShowProgress($io, $waitSeconds, 'Processing embedding update...');
            $this->displayUserEmbedding($io, $userId, 'AFTER NORMAL SEARCH');
            $io->newLine();

            if (!empty($products)) {
                // 3. Product View Event
                $product = $products[array_rand($products)];
                $io->writeln('<fg=cyan>📍 Event 3/4: Product View</fg=cyan>');
                $io->writeln("   👁️  Viewing: <comment>{$product['name']} (ID: {$product['id']})</comment>");
                
                $published = $this->publishEvent(
                    userId: $userId,
                    eventType: EventType::PRODUCT_VIEW,
                    productId: $product['id'],
                    metadata: ['product_name' => $product['name']]
                );
                
                if ($published) {
                    $successfulPublishes++;
                    $io->writeln('   ✅ Event published to queue');
                } else {
                    $io->writeln('   ❌ Failed to publish event');
                }
                $totalEvents++;
                
                $this->waitAndShowProgress($io, $waitSeconds, 'Processing embedding update...');
                $this->displayUserEmbedding($io, $userId, 'AFTER PRODUCT VIEW');
                $io->newLine();

                // 4. Product Purchase Event
                $product = $products[array_rand($products)];
                $io->writeln('<fg=cyan>📍 Event 4/4: Product Purchase</fg=cyan>');
                $io->writeln("   💰 Purchasing: <comment>{$product['name']} (ID: {$product['id']})</comment>");
                
                $published = $this->publishEvent(
                    userId: $userId,
                    eventType: EventType::PRODUCT_PURCHASE,
                    productId: $product['id'],
                    metadata: ['product_name' => $product['name']]
                );
                
                if ($published) {
                    $successfulPublishes++;
                    $io->writeln('   ✅ Event published to queue');
                } else {
                    $io->writeln('   ❌ Failed to publish event');
                }
                $totalEvents++;
                
                $this->waitAndShowProgress($io, $waitSeconds, 'Processing embedding update...');
                $this->displayUserEmbedding($io, $userId, 'AFTER PURCHASE');
                $io->newLine();
            }

            $io->newLine();
        }

        // Summary
        $io->success([
            'Demo completed successfully!',
            sprintf('Total users processed: %d', count($users)),
            sprintf('Total events generated: %d', $totalEvents),
            sprintf('Successfully published: %d', $successfulPublishes),
            sprintf('Failed: %d', $totalEvents - $successfulPublishes),
        ]);

        $io->note([
            'The embeddings are updated asynchronously via RabbitMQ.',
            'Workers process messages in the background.',
            '',
            'To monitor processing:',
            '  • Check worker logs: docker-compose logs worker -f',
            '  • View RabbitMQ management: http://localhost:15672 (guest/guest)',
            '  • Query MongoDB: docker-compose exec mongodb mongosh',
            '',  
            'If embeddings are not appearing:',
            '  1. Verify workers are running: docker-compose ps worker',
            '  2. Check RabbitMQ connections',
            '  3. Verify OpenAI API key is set in .env.local',
            '  4. Check worker logs for errors',
        ]);

        return Command::SUCCESS;
    }

    /**
     * Publish event to RabbitMQ queue
     */
    private function publishEvent(
        int $userId,
        EventType $eventType,
        ?string $searchPhrase = null,
        ?int $productId = null,
        array $metadata = []
    ): bool {
        // Generate SHA-256 hash for idempotency
        $dataForHash = json_encode([
            'user_id' => $userId,
            'event_type' => $eventType->value,
            'occurred_at' => (new DateTimeImmutable())->format('c'),
            'search_phrase' => $searchPhrase,
            'product_id' => $productId,
            'random' => bin2hex(random_bytes(16)), // Ensure uniqueness
        ]);
        
        $messageId = hash('sha256', $dataForHash);

        $message = new UpdateUserEmbeddingMessage(
            messageId: $messageId,
            userId: $userId,
            eventType: $eventType,
            occurredAt: new DateTimeImmutable(),
            searchPhrase: $searchPhrase,
            productId: $productId,
            metadata: $metadata
        );

        return $this->publisher->publish($message);
    }

    /**
     * Display user embedding state
     */
    private function displayUserEmbedding(SymfonyStyle $io, int $userId, string $label): void
    {
        try {
            $embedding = $this->embeddingRepository->findByUserId($userId);
        } catch (\Exception $e) {
            $io->writeln("   <fg=red>❌ {$label}: Error reading embedding: {$e->getMessage()}</fg=red>");
            return;
        }
        
        if ($embedding === null) {
            $io->writeln("   <fg=yellow>🔸 {$label}: No embedding yet (waiting for async processing...)</fg=yellow>");
            return;
        }

        $vector = $embedding->vector;
        $dimensions = count($vector);
        
        // Calculate some statistics for visualization
        $magnitude = sqrt(array_sum(array_map(fn($v) => $v * $v, $vector)));
        $mean = array_sum($vector) / $dimensions;
        $firstValues = array_slice($vector, 0, 5);
        $lastValues = array_slice($vector, -5);
        
        $io->writeln(sprintf(
            "   <fg=green>✨ %s:</fg=green> Dimensions: %d | Magnitude: %.4f | Mean: %.6f",
            $label,
            $dimensions,
            $magnitude,
            $mean
        ));
        
        $io->writeln(sprintf(
            "      First 5: [%s]",
            implode(', ', array_map(fn($v) => sprintf('%.4f', $v), $firstValues))
        ));
        
        $io->writeln(sprintf(
            "      Last 5:  [%s]",
            implode(', ', array_map(fn($v) => sprintf('%.4f', $v), $lastValues))
        ));
        
        $io->writeln(sprintf(
            "      Updated: <comment>%s</comment>",
            $embedding->lastUpdatedAt->format('Y-m-d H:i:s')
        ));
    }

    /**
     * Wait with progress indication
     */
    private function waitAndShowProgress(SymfonyStyle $io, int $seconds, string $message): void
    {
        if ($seconds <= 0) {
            return;
        }

        $io->writeln("   ⏳ {$message}");
        sleep($seconds);
    }

    /**
     * Get simulated users for demo
     * 
     * @return array<array{id: int, label: string}>
     */
    private function getSimulatedUsers(int $limit): array
    {
        $users = [];
        for ($i = 1; $i <= $limit; $i++) {
            $users[] = [
                'id' => $i,
                'label' => "demo-user-{$i}@example.com",
            ];
        }
        return $users;
    }

    /**
     * Get simulated products for demo
     * 
     * @return array<array{id: int, name: string}>
     */
    private function getSimulatedProducts(int $limit): array
    {
        $productNames = [
            'Laptop Dell XPS 15',
            'iPhone 15 Pro',
            'Samsung Galaxy S24',
            'Sony WH-1000XM5 Headphones',
            'Logitech MX Master 3S Mouse',
            'Mechanical Keyboard RGB',
            'USB-C Hub Multiport',
            'Phone Case Leather',
            'Screen Protector Premium',
            'External SSD 2TB',
            'Bluetooth Speaker JBL',
            'Apple Watch Series 9',
            'iPad Air 5th Gen',
            'Webcam Logitech 4K',
            'Blue Yeti Microphone',
        ];

        $products = [];
        for ($i = 1; $i <= min($limit, count($productNames)); $i++) {
            $products[] = [
                'id' => $i + 100, // Offset to avoid collision with user IDs
                'name' => $productNames[$i - 1],
            ];
        }
        return $products;
    }

    /**
     * Get random users from database
     * 
     * @return array<array{id: string, email: string}>
     */
    private function getRandomUsers(int $limit): array
    {
        // Using HEX to convert BINARY(16) UUID to readable format
        $sql = 'SELECT BIN_TO_UUID(id) as id, email FROM users ORDER BY RAND() LIMIT :limit';
        
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->bindValue('limit', $limit, \PDO::PARAM_INT);
            $result = $stmt->executeQuery();
            
            return $result->fetchAllAssociative();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get random products from database
     * 
     * @return array<array{id: string, name: string}>
     */
    private function getRandomProducts(int $limit): array
    {
        // Using HEX to convert BINARY(16) UUID to readable format
        $sql = 'SELECT BIN_TO_UUID(id) as id, name FROM products ORDER BY RAND() LIMIT :limit';
        
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->bindValue('limit', $limit, \PDO::PARAM_INT);
            $result = $stmt->executeQuery();
            
            return $result->fetchAllAssociative();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get random search phrase for simulation
     */
    private function getRandomSearchPhrase(): string
    {
        $phrases = [
            'laptop computer',
            'wireless headphones',
            'gaming mouse',
            'mechanical keyboard',
            'USB cable',
            'phone case',
            'screen protector',
            'external hard drive',
            'bluetooth speaker',
            'smartwatch',
            'tablet',
            'webcam',
            'microphone',
            'monitor',
            'desk lamp',
        ];

        return $phrases[array_rand($phrases)];
    }
}
