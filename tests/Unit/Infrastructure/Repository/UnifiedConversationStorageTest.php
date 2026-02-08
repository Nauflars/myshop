<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Repository;

use App\Infrastructure\Repository\UnifiedConversationStorage;
use PHPUnit\Framework\TestCase;
use Predis\Client;
use Psr\Log\LoggerInterface;

/**
 * Tests for UnifiedConversationStorage (spec-012)
 * 
 * Validates FIFO history management, key patterns, TTL, and metadata tracking
 */
class UnifiedConversationStorageTest extends TestCase
{
    private Client $redisMock;
    private LoggerInterface $loggerMock;
    private UnifiedConversationStorage $storage;
    private const DEFAULT_TTL = 1800; // 30 minutes

    protected function setUp(): void
    {
        $this->redisMock = $this->createMock(Client::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);
        $this->storage = new UnifiedConversationStorage(
            $this->redisMock,
            $this->loggerMock,
            self::DEFAULT_TTL
        );
    }

    /**
     * @test T005: Key pattern generation
     */
    public function testMakeKeyGeneratesCorrectPattern(): void
    {
        $key = $this->storage->makeKey('client', 'user123', 'conv-uuid', 'history');
        
        $this->assertEquals('conversation:client:user123:conv-uuid:history', $key);
    }

    /**
     * @test T006: FIFO history - adding messages beyond limit
     */
    public function testAddMessageToHistoryEnforcesFIFOLimit(): void
    {
        $role = 'client';
        $userId = 'user123';
        $conversationId = 'conv-uuid';
        
        // Mock existing history with 10 messages (at limit)
        $existingHistory = [];
        for ($i = 1; $i <= 10; $i++) {
            $existingHistory[] = [
                'role' => 'user',
                'content' => "Message $i",
                'timestamp' => time() - (100 - $i), // Oldest first
            ];
        }
        
        $this->redisMock
            ->expects($this->once())
            ->method('get')
            ->with('conversation:client:user123:conv-uuid:history')
            ->willReturn(json_encode($existingHistory));
        
        // When adding 11th message, expect oldest to be removed
        $this->redisMock
            ->expects($this->once())
            ->method('setex')
            ->with(
                'conversation:client:user123:conv-uuid:history',
                self::DEFAULT_TTL,
                $this->callback(function ($json) {
                    $history = json_decode($json, true);
                    
                    // Should have exactly 10 messages
                    if (count($history) !== 10) {
                        return false;
                    }
                    
                    // First message should now be "Message 2" (Message 1 removed)
                    if ($history[0]['content'] !== 'Message 2') {
                        return false;
                    }
                    
                    // Last message should be the new one
                    if ($history[9]['content'] !== 'Message 11') {
                        return false;
                    }
                    
                    return true;
                })
            )
            ->willReturn(true);
        
        $result = $this->storage->addMessageToHistory(
            $role,
            $userId,
            $conversationId,
            'user',
            'Message 11',
            self::DEFAULT_TTL
        );
        
        $this->assertTrue($result);
    }

    /**
     * @test T007: Metadata initialization
     */
    public function testInitializeMetaCreatesCorrectStructure(): void
    {
        $role = 'client';
        $userId = 'user123';
        $conversationId = 'conv-uuid';
        
        $this->redisMock
            ->expects($this->once())
            ->method('setex')
            ->with(
                'conversation:client:user123:conv-uuid:meta',
                self::DEFAULT_TTL,
                $this->callback(function ($json) use ($role) {
                    $meta = json_decode($json, true);
                    
                    // Check required fields
                    if (!isset($meta['role']) || $meta['role'] !== $role) {
                        return false;
                    }
                    
                    if (!isset($meta['created_at']) || !is_int($meta['created_at'])) {
                        return false;
                    }
                    
                    if (!isset($meta['last_activity']) || !is_int($meta['last_activity'])) {
                        return false;
                    }
                    
                    return true;
                })
            )
            ->willReturn(true);
        
        $result = $this->storage->initializeMeta($role, $userId, $conversationId, self::DEFAULT_TTL);
        
        $this->assertTrue($result);
    }

    /**
     * @test T015: State persistence and retrieval
     */
    public function testSetStateAndGetStateWorkCorrectly(): void
    {
        $role = 'client';
        $userId = 'user123';
        $conversationId = 'conv-uuid';
        $state = [
            'flow' => 'product_browsing',
            'turn_count' => 5,
            'selected_products' => [1, 2, 3],
        ];
        
        // Mock setState
        $this->redisMock
            ->expects($this->once())
            ->method('setex')
            ->with(
                'conversation:client:user123:conv-uuid:state',
                self::DEFAULT_TTL,
                json_encode($state)
            )
            ->willReturn(true);
        
        $setResult = $this->storage->setState($role, $userId, $conversationId, $state, self::DEFAULT_TTL);
        $this->assertTrue($setResult);
        
        // Mock getState
        $this->redisMock
            ->expects($this->once())
            ->method('get')
            ->with('conversation:client:user123:conv-uuid:state')
            ->willReturn(json_encode($state));
        
        $retrievedState = $this->storage->getState($role, $userId, $conversationId);
        $this->assertEquals($state, $retrievedState);
    }

    /**
     * @test T015: TTL refresh for all keys
     */
    public function testRefreshTtlUpdatesAllKeys(): void
    {
        $role = 'client';
        $userId = 'user123';
        $conversationId = 'conv-uuid';
        
        // Expect TTL refresh for all 3 keys
        $this->redisMock
            ->expects($this->exactly(3))
            ->method('expire')
            ->willReturnCallback(function($key, $ttl) {
                $this->assertEquals(self::DEFAULT_TTL, $ttl);
                
                // Verify it's called for all 3 key types
                $this->assertMatchesRegularExpression(
                    '/conversation:client:user123:conv-uuid:(history|state|meta)/',
                    $key
                );
                
                return 1; // 1 = success in Redis
            });
        
        $result = $this->storage->refreshTtl($role, $userId, $conversationId, self::DEFAULT_TTL);
        $this->assertTrue($result);
    }

    /**
     * @test T015: Delete removes all conversation keys
     */
    public function testDeleteRemovesAllKeys(): void
    {
        $role = 'client';
        $userId = 'user123';
        $conversationId = 'conv-uuid';
        
        // Expect deletion of all 3 keys
        $this->redisMock
            ->expects($this->exactly(3))
            ->method('del')
            ->willReturnCallback(function($key) {
                $this->assertMatchesRegularExpression(
                    '/conversation:client:user123:conv-uuid:(history|state|meta)/',
                    $key
                );
                return 1;
            });
        
        $result = $this->storage->delete($role, $userId, $conversationId);
        $this->assertTrue($result);
    }

    /**
     * @test T015: Exists checks all required keys
     */
    public function testExistsReturnsTrueWhenAllKeysPresent(): void
    {
        $role = 'client';
        $userId = 'user123';
        $conversationId = 'conv-uuid';
        
        // Mock all 3 keys existing
        $this->redisMock
            ->expects($this->exactly(3))
            ->method('exists')
            ->willReturn(1); // 1 = key exists in Redis
        
        $result = $this->storage->exists($role, $userId, $conversationId);
        $this->assertTrue($result);
    }

    /**
     * @test T015: Exists returns false when any key missing
     */
    public function testExistsReturnsFalseWhenAnyKeyMissing(): void
    {
        $role = 'client';
        $userId = 'user123';
        $conversationId = 'conv-uuid';
        
        // Mock history exists, but state missing
        $this->redisMock
            ->expects($this->exactly(3))
            ->method('exists')
            ->willReturnOnConsecutiveCalls(1, 0, 1); // middle key missing
        
        $result = $this->storage->exists($role, $userId, $conversationId);
        $this->assertFalse($result);
    }
}
