<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Service;

use App\Domain\Entity\AdminAssistantConversation;
use App\Domain\Entity\AdminAssistantMessage;
use App\Domain\Entity\User;
use App\Infrastructure\Repository\AdminAssistantRepository;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

/**
 * AdminConversationManager - Manages admin assistant conversation sessions
 * 
 * Part of spec-007: Admin Virtual Assistant
 * Handles conversation persistence and session management
 */
class AdminConversationManager
{
    public function __construct(
        private readonly AdminAssistantRepository $repository
    ) {
    }

    /**
     * Get or create active conversation for admin user
     */
    public function getOrCreateConversation(User $adminUser, ?string $sessionId = null): AdminAssistantConversation
    {
        // Try to find by session ID first
        if ($sessionId !== null) {
            $conversation = $this->repository->findBySessionId($sessionId);
            if ($conversation !== null && $conversation->getAdminUser()->getId() === $adminUser->getId()) {
                return $conversation;
            }
        }

        // Fall back to finding active conversation for user
        $conversation = $this->repository->findActiveByUser($adminUser);
        
        if ($conversation === null) {
            $conversation = new AdminAssistantConversation($adminUser, $sessionId);
            $this->repository->save($conversation);
        }

        return $conversation;
    }

    /**
     * Save admin message to conversation
     */
    public function saveAdminMessage(AdminAssistantConversation $conversation, string $messageText): AdminAssistantMessage
    {
        $message = new AdminAssistantMessage(
            $conversation,
            AdminAssistantMessage::SENDER_ADMIN,
            $messageText
        );

        $this->repository->save($conversation);

        return $message;
    }

    /**
     * Save assistant response to conversation
     */
    public function saveAssistantMessage(
        AdminAssistantConversation $conversation,
        string $messageText,
        ?array $toolInvocations = null
    ): AdminAssistantMessage {
        $message = new AdminAssistantMessage(
            $conversation,
            AdminAssistantMessage::SENDER_ASSISTANT,
            $messageText
        );

        if ($toolInvocations !== null && !empty($toolInvocations)) {
            foreach ($toolInvocations as $invocation) {
                $message->addToolInvocation(
                    $invocation['tool'] ?? 'unknown',
                    $invocation['parameters'] ?? [],
                    $invocation['result'] ?? null
                );
            }
        }

        $this->repository->save($conversation);

        return $message;
    }

    /**
     * Convert conversation to MessageBag for AI agent
     */
    public function conversationToMessageBag(AdminAssistantConversation $conversation): MessageBag
    {
        $messages = [];

        foreach ($conversation->getMessages() as $message) {
            if ($message->isFromAdmin()) {
                $messages[] = Message::ofUser($message->getMessageText());
            } else {
                $messages[] = Message::ofAssistant($message->getMessageText());
            }
        }

        return new MessageBag(...$messages);
    }

    /**
     * End conversation session
     */
    public function endConversation(AdminAssistantConversation $conversation): void
    {
        $conversation->end();
        $this->repository->save($conversation);
    }

    /**
     * Update conversational context
     */
    public function updateContext(AdminAssistantConversation $conversation, string $key, mixed $value): void
    {
        $conversation->updateContext($key, $value);
        $this->repository->save($conversation);
    }

    /**
     * Get context value
     */
    public function getContextValue(AdminAssistantConversation $conversation, string $key): mixed
    {
        return $conversation->getContextValue($key);
    }

    /**
     * Clear context
     */
    public function clearContext(AdminAssistantConversation $conversation): void
    {
        $conversation->clearContext();
        $this->repository->save($conversation);
    }

    /**
     * Get recent conversations for user
     *
     * @return AdminAssistantConversation[]
     */
    public function getRecentConversations(User $adminUser, int $limit = 10): array
    {
        return $this->repository->findRecentByUser($adminUser, $limit);
    }

    /**
     * Create a new conversation (ends any active ones first)
     */
    public function startNewConversation(User $adminUser, ?string $sessionId = null): AdminAssistantConversation
    {
        // End any active conversations
        $activeConversation = $this->repository->findActiveByUser($adminUser);
        if ($activeConversation !== null) {
            $this->endConversation($activeConversation);
        }

        $conversation = new AdminAssistantConversation($adminUser, $sessionId);
        $this->repository->save($conversation);

        return $conversation;
    }
}
