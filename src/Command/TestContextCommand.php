<?php

declare(strict_types=1);

namespace App\Command;

use App\Domain\Repository\ContextStorageInterface;
use App\Domain\ValueObject\ConversationContext;
use DateTimeImmutable;
use Predis\Client;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Command to test context storage functionality
 * 
 * Tests Redis connection, context CRUD operations, and TTL behavior.
 */
#[AsCommand(
    name: 'app:test-context',
    description: 'Test context storage operations (Redis connectivity, CRUD, TTL)',
)]
class TestContextCommand extends Command
{
    public function __construct(
        private readonly Client $redis,
        private readonly ContextStorageInterface $contextStorage
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Context Storage Test Suite');

        // Test 1: Redis Connection
        $io->section('Test 1: Redis Connection');
        try {
            $pong = $this->redis->ping();
            $pongString = (string) $pong;
            if ($pongString === 'PONG') {
                $io->success('✓ Redis connection successful');
            } else {
                $io->error('✗ Redis connection failed: unexpected response');
                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $io->error("✗ Redis connection failed: {$e->getMessage()}");
            return Command::FAILURE;
        }

        // Test 2: Create Test Context
        $io->section('Test 2: Store Test Context');
        $testKey = 'test:context:' . uniqid();
        
        try {
            $testContext = new TestConversationContext(
                userId: 'test-user-123',
                flow: 'product_browsing',
                lastTool: 'GetProductsTool',
                turnCount: 1,
                createdAt: new DateTimeImmutable(),
                updatedAt: new DateTimeImmutable(),
                testData: 'This is a test context'
            );

            $this->contextStorage->set($testKey, $testContext, 60); // 60 second TTL
            $io->success("✓ Test context stored with key: {$testKey}");
        } catch (\Exception $e) {
            $io->error("✗ Failed to store context: {$e->getMessage()}");
            return Command::FAILURE;
        }

        // Test 3: Retrieve Context
        $io->section('Test 3: Retrieve Context');
        try {
            $retrieved = $this->contextStorage->get($testKey);
            
            if ($retrieved === null) {
                $io->error('✗ Failed to retrieve context: returned null');
                return Command::FAILURE;
            }

            if ($retrieved->getUserId() !== 'test-user-123') {
                $io->error('✗ Retrieved context has incorrect data');
                return Command::FAILURE;
            }

            $io->success('✓ Context retrieved successfully');
            $io->writeln([
                "  User ID: {$retrieved->getUserId()}",
                "  Flow: {$retrieved->getFlow()}",
                "  Last Tool: {$retrieved->getLastTool()}",
                "  Turn Count: {$retrieved->getTurnCount()}"
            ]);
        } catch (\Exception $e) {
            $io->error("✗ Failed to retrieve context: {$e->getMessage()}");
            return Command::FAILURE;
        }

        // Test 4: Check Existence
        $io->section('Test 4: Check Context Existence');
        if ($this->contextStorage->exists($testKey)) {
            $io->success('✓ Context exists check passed');
        } else {
            $io->error('✗ Context exists check failed');
            return Command::FAILURE;
        }

        // Test 5: TTL Operations
        $io->section('Test 5: TTL Operations');
        try {
            $ttl = $this->contextStorage->getTtl($testKey);
            if ($ttl === null) {
                $io->error('✗ Failed to get TTL');
                return Command::FAILURE;
            }
            $io->writeln("  Current TTL: {$ttl} seconds");

            // Refresh TTL
            $refreshed = $this->contextStorage->refreshTtl($testKey, 120);
            if (!$refreshed) {
                $io->error('✗ Failed to refresh TTL');
                return Command::FAILURE;
            }

            $newTtl = $this->contextStorage->getTtl($testKey);
            $io->writeln("  New TTL: {$newTtl} seconds");
            
            if ($newTtl > $ttl) {
                $io->success('✓ TTL refresh successful');
            } else {
                $io->error('✗ TTL did not increase after refresh');
                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $io->error("✗ TTL operations failed: {$e->getMessage()}");
            return Command::FAILURE;
        }

        // Test 6: Delete Context
        $io->section('Test 6: Delete Context');
        try {
            $deleted = $this->contextStorage->delete($testKey);
            if (!$deleted) {
                $io->error('✗ Failed to delete context');
                return Command::FAILURE;
            }

            $stillExists = $this->contextStorage->exists($testKey);
            if ($stillExists) {
                $io->error('✗ Context still exists after deletion');
                return Command::FAILURE;
            }

            $io->success('✓ Context deleted successfully');
        } catch (\Exception $e) {
            $io->error("✗ Delete operation failed: {$e->getMessage()}");
            return Command::FAILURE;
        }

        // Test 7: Expired Context
        $io->section('Test 7: Context Expiration');
        try {
            $shortKey = 'test:context:short-' . uniqid();
            $shortContext = new TestConversationContext(
                userId: 'test-user-456',
                flow: 'checkout',
                lastTool: null,
                turnCount: 1,
                createdAt: new DateTimeImmutable(),
                updatedAt: new DateTimeImmutable(),
                testData: 'This will expire soon'
            );

            $this->contextStorage->set($shortKey, $shortContext, 2); // 2 second TTL
            $io->writeln('  Stored context with 2 second TTL');
            $io->writeln('  Waiting 3 seconds for expiration...');
            sleep(3);

            $expired = $this->contextStorage->get($shortKey);
            if ($expired === null) {
                $io->success('✓ Expired context correctly returned null');
            } else {
                $io->error('✗ Expired context was still retrieved');
                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $io->error("✗ Expiration test failed: {$e->getMessage()}");
            return Command::FAILURE;
        }

        $io->success('All context storage tests passed! ✓');
        
        return Command::SUCCESS;
    }
}

/**
 * Test implementation of ConversationContext for testing purposes
 */
class TestConversationContext extends ConversationContext
{
    public function __construct(
        string $userId,
        string $flow,
        ?string $lastTool,
        int $turnCount,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
        private readonly string $testData
    ) {
        parent::__construct($userId, $flow, $lastTool, $turnCount, $createdAt, $updatedAt);
    }

    public function toArray(): array
    {
        return [
            'userId' => $this->userId,
            'flow' => $this->flow,
            'lastTool' => $this->lastTool,
            'turnCount' => $this->turnCount,
            'createdAt' => $this->createdAt->format(\DateTimeInterface::RFC3339),
            'updatedAt' => $this->updatedAt->format(\DateTimeInterface::RFC3339),
            'testData' => $this->testData
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            userId: $data['userId'],
            flow: $data['flow'],
            lastTool: $data['lastTool'] ?? null,
            turnCount: $data['turnCount'],
            createdAt: new DateTimeImmutable($data['createdAt']),
            updatedAt: new DateTimeImmutable($data['updatedAt']),
            testData: $data['testData'] ?? ''
        );
    }

    public function toPromptContext(): string
    {
        return "Test context for user {$this->userId} in {$this->flow} flow";
    }
}
