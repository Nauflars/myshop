<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Domain\Entity\User;
use App\Domain\ValueObject\Email;
use App\Entity\Conversation;
use App\Entity\ConversationMessage;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ConversationMessage entity
 */
class ConversationMessageTest extends TestCase
{
    public function testCreateMessageWithAllProperties(): void
    {
        $user = new User('Test', new Email('test@example.com'), 'hash');
        $conversation = new Conversation($user);
        
        $message = new ConversationMessage(
            $conversation,
            'user',
            'Hello, how can I help?'
        );
        
        $this->assertSame($conversation, $message->getConversation());
        $this->assertSame('user', $message->getRole());
        $this->assertSame('Hello, how can I help?', $message->getContent());
        $this->assertInstanceOf(\DateTime::class, $message->getCreatedAt());
    }

    public function testSetAndGetMetadata(): void
    {
        $user = new User('Test', new Email('test@example.com'), 'hash');
        $conversation = new Conversation($user);
        $message = new ConversationMessage($conversation, 'assistant', 'Response');
        
        $metadata = ['tool' => 'search', 'duration' => 150];
        $message->setMetadata($metadata);
        
        $this->assertSame($metadata, $message->getMetadata());
    }

    public function testDefaultMetadataIsEmptyArray(): void
    {
        $user = new User('Test', new Email('test@example.com'), 'hash');
        $conversation = new Conversation($user);
        $message = new ConversationMessage($conversation, 'user', 'Query');
        
        $this->assertSame([], $message->getMetadata());
    }

    public function testCreatedAtIsSetAutomatically(): void
    {
        $user = new User('Test', new Email('test@example.com'), 'hash');
        $conversation = new Conversation($user);
        $message = new ConversationMessage($conversation, 'system', 'Info');
        
        $before = new \DateTime('-1 second');
        $after = new \DateTime('+1 second');
        
        $this->assertGreaterThan($before, $message->getCreatedAt());
        $this->assertLessThan($after, $message->getCreatedAt());
    }
}
