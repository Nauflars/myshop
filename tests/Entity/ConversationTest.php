<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Domain\Entity\User;
use App\Domain\ValueObject\Email;
use App\Entity\Conversation;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Conversation entity
 */
class ConversationTest extends TestCase
{
    public function testCreateConversationWithUser(): void
    {
        $user = new User('John Doe', new Email('john@example.com'), 'password_hash');
        $conversation = new Conversation($user);
        
        $this->assertSame($user, $conversation->getUser());
        $this->assertInstanceOf(\DateTime::class, $conversation->getCreatedAt());
        $this->assertInstanceOf(\DateTime::class, $conversation->getUpdatedAt());
        $this->assertCount(0, $conversation->getMessages());
    }

    public function testSetAndGetTitle(): void
    {
        $user = new User('Jane', new Email('jane@example.com'), 'hash');
        $conversation = new Conversation($user);
        
        $conversation->setTitle('Shopping Assistance');
        
        $this->assertSame('Shopping Assistance', $conversation->getTitle());
    }

    public function testDefaultTitleIsNull(): void
    {
        $user = new User('Bob', new Email('bob@example.com'), 'hash');
        $conversation = new Conversation($user);
        
        $this->assertNull($conversation->getTitle());
    }

    public function testUpdatedAtIsSetOnCreation(): void
    {
        $user = new User('Alice', new Email('alice@example.com'), 'hash');
        $conversation = new Conversation($user);
        
        $before = new \DateTime('-1 second');
        $after = new \DateTime('+1 second');
        
        $this->assertGreaterThan($before, $conversation->getUpdatedAt());
        $this->assertLessThan($after, $conversation->getUpdatedAt());
    }

    public function testGetMessagesReturnsCollection(): void
    {
        $user = new User('Charlie', new Email('charlie@example.com'), 'hash');
        $conversation = new Conversation($user);
        
        $messages = $conversation->getMessages();
        
        $this->assertIsIterable($messages);
        $this->assertCount(0, $messages);
    }
}
