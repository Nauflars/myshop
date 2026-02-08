<?php

declare(strict_types=1);

namespace Tests\Integration\Application\Service;

use App\Application\Service\UnifiedCustomerContextManager;
use App\Infrastructure\Repository\UnifiedConversationStorage;
use PHPUnit\Framework\TestCase;
use Predis\Client;
use Psr\Log\LoggerInterface;

/**
 * Integration tests for UnifiedCustomerContextManager (spec-012)
 * 
 * Tests conversation state management, history caching, and MessageBag construction
 */
class UnifiedCustomerContextManagerTest extends TestCase
{
    private Client $redisMock;
    private LoggerInterface $loggerMock;
    private UnifiedConversationStorage $storage;
    private UnifiedCustomerContextManager $manager;
    private const TTL = 1800;

    protected function setUp(): void
    {
        $this->redisMock = $this->createMock(Client::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);
        
        $this->storage = new UnifiedConversationStorage(
            $this->redisMock,
            $this->loggerMock,
            self::TTL
        );
        
        $this->manager = new UnifiedCustomerContextManager(
            $this->storage,
            $this->loggerMock,
            self::TTL
        );
    }

    /**
     * @test T016: Get or create conversation - new conversation
     */
    public function testGetOrCreateConversationCreatesNewWhenNotExists(): void
    {
        $userId = 'user123';
        
        // Mock: no existing conversation
        $this->redisMock
            ->method('exists')
            ->willReturn(0);
        
        // Mock: initialize meta
        $this->redisMock
            ->expects($this->atLeastOnce())
            ->method('setex')
            ->willReturn(true);
        
        $conversation = $this->manager->getOrCreateConversation($userId);
        
        // Should return new conversation with UUID
        $this->assertIsArray($conversation);
        $this->assertArrayHasKey('conversationId', $conversation);
        $this->assertArrayHasKey('state', $conversation);
        $this->assertArrayHasKey('history', $conversation);
        
        // New conversation should have empty history
        $this->assertEmpty($conversation['history']);
        
        // State should have default structure
        $this->assertEquals('browsing', $conversation['state']['flow']);
        $this->assertEquals(0, $conversation['state']['turn_count']);
    }

    /**
     * @test T016: Get or create conversation - load existing
     */
    public function testGetOrCreateConversationLoadsExistingWhenProvided(): void
    {
        $userId = 'user123';
        $conversationId = 'existing-uuid';
        
        // Mock: conversation exists
        $this->redisMock
            ->method('exists')
            ->willReturn(1);
        
        // Mock: load existing state
        $existingState = [
            'flow' => 'cart_management',
            'turn_count' => 5,
            'cart_items' => [['product_id' => 1, 'quantity' => 2]],
        ];
        $this->redisMock
            ->method('get')
            ->willReturnCallback(function($key) use ($existingState) {
                if (str_contains($key, ':state')) {
                    return json_encode($existingState);
                }
                if (str_contains($key, ':history')) {
                    return json_encode([
                        ['role' => 'user', 'content' => 'Hello', 'timestamp' => time()],
                        ['role' => 'assistant', 'content' => 'Hi!', 'timestamp' => time()],
                    ]);
                }
                return null;
            });
        
        $conversation = $this->manager->getOrCreateConversation($userId, $conversationId);
        
        $this->assertEquals($conversationId, $conversation['conversationId']);
        $this->assertEquals('cart_management', $conversation['state']['flow']);
        $this->assertEquals(5, $conversation['state']['turn_count']);
        $this->assertCount(2, $conversation['history']);
    }

    /**
     * @test T016: Add message and verify history
     */
    public function testAddMessageAddsToHistory(): void
    {
        $userId = 'user123';
        $conversationId = 'conv-uuid';
        
        // Mock: get existing history (empty)
        $this->redisMock
            ->expects($this->once())
            ->method('get')
            ->willReturn(json_encode([]));
        
        // Mock: setex saves new history with added message
        $this->redisMock
            ->expects($this->atLeastOnce())
            ->method('setex')
            ->willReturn(true);
        
        $result = $this->manager->addMessage($userId, $conversationId, 'user', 'Test message');
        
        $this->assertTrue($result);
    }

    /**
     * @test T016: Build MessageBag context with state and history
     */
    public function testBuildMessageBagContextIncludesStateAndHistory(): void
    {
        $userId = 'user123';
        $conversationId = 'conv-uuid';
        
        // Mock: conversation exists with state and history
        $this->redisMock
            ->method('get')
            ->willReturnCallback(function($key) {
                if (str_contains($key, ':state')) {
                    return json_encode([
                        'flow' => 'checkout',
                        'turn_count' => 3,
                        'cart_items' => [['product_id' => 1, 'quantity' => 1]],
                    ]);
                }
                if (str_contains($key, ':history')) {
                    return json_encode([
                        ['role' => 'user', 'content' => 'I want to buy shoes', 'timestamp' => time()],
                        ['role' => 'assistant', 'content' => 'Great! Here are our shoes.', 'timestamp' => time()],
                        ['role' => 'user', 'content' => 'Add to cart', 'timestamp' => time()],
                    ]);
                }
                return null;
            });
        
        $messages = $this->manager->buildMessageBagContext($userId, $conversationId);
        
        // Should have: system message with state + history
        $this->assertNotEmpty($messages);
        $this->assertGreaterThanOrEqual(3, count($messages)); // At least 3 history messages
        
        // First message should be system with state context
        $this->assertEquals('system', $messages[0]['role']);
        $this->assertStringContainsString('checkout', $messages[0]['content']);
        
        // Subsequent messages should be history
        $this->assertEquals('user', $messages[1]['role']);
        $this->assertEquals('I want to buy shoes', $messages[1]['content']);
    }

    /**
     * @test T016: Update state after tool execution
     */
    public function testUpdateAfterToolExecutionUpdatesFlow(): void
    {
        $userId = 'user123';
        $conversationId = 'conv-uuid';
        
        // Mock: get current state
        $this->redisMock
            ->method('get')
            ->willReturnCallback(function($key) {
                if (str_contains($key, ':state')) {
                    return json_encode([
                        'flow' => 'browsing',
                        'turn_count' => 1,
                        'selected_products' => [],
                    ]);
                }
                return null;
            });
        
        // Mock: setex updates state
        $this->redisMock
            ->expects($this->atLeastOnce())
            ->method('setex')
            ->with(
                $this->stringContains(':state'),
                self::TTL,
                $this->callback(function($json) {
                    $state = json_decode($json, true);
                    
                    // flow should be updated to cart_management
                    return $state['flow'] === 'cart_management'
                        && $state['last_tool'] === 'AddToCart'
                        && $state['turn_count'] === 2;
                })
            )
            ->willReturn(true);
        
        $result = $this->manager->updateAfterToolExecution(
            $userId,
            $conversationId,
            'AddToCart',
            ['product_id' => 1, 'quantity' => 2]
        );
        
        $this->assertTrue($result);
    }

    /**
     * @test T016: Get default state structure
     */
    public function testDefaultStateHasRequiredFields(): void
    {
        $userId = 'user123';
        
        // Mock: new conversation
        $this->redisMock->method('exists')->willReturn(0);
        $this->redisMock->method('setex')->willReturn(true);
        
        $conversation = $this->manager->getOrCreateConversation($userId);
        $state = $conversation['state'];
        
        // Verify all required fields
        $this->assertArrayHasKey('flow', $state);
        $this->assertArrayHasKey('last_tool', $state);
        $this->assertArrayHasKey('turn_count', $state);
        $this->assertArrayHasKey('selected_products', $state);
        $this->assertArrayHasKey('cart_items', $state);
        $this->assertArrayHasKey('checkout_step', $state);
        $this->assertArrayHasKey('language', $state);
        
        // Verify initial values
        $this->assertEquals('browsing', $state['flow']);
        $this->assertEquals(0, $state['turn_count']);
        $this->assertEquals('en', $state['language']);
    }
}
