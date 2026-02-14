<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Service;

use App\Application\UseCase\AI\Conversation\ClearConversation;
use App\Application\UseCase\AI\Conversation\LoadConversation;
use App\Application\UseCase\AI\Conversation\SaveConversation;
use App\Domain\Entity\User;

/**
 * ConversationManager Service.
 *
 * Infrastructure service for managing conversation persistence in the chatbot.
 * Orchestrates conversation use cases and provides a clean API for controllers.
 *
 * Updated in spec-003 to use database persistence instead of sessions.
 */
final class ConversationManager
{
    public function __construct(
        private readonly SaveConversation $saveConversation,
        private readonly LoadConversation $loadConversation,
        private readonly ClearConversation $clearConversation,
    ) {
    }

    /**
     * Save a user message to the conversation.
     */
    public function saveUserMessage(User $user, ?string $conversationId, string $content): array
    {
        return $this->saveConversation->execute($user, $conversationId, 'user', $content);
    }

    /**
     * Save an assistant response to the conversation.
     */
    public function saveAssistantMessage(
        User $user,
        string $conversationId,
        string $content,
        ?array $toolCalls = null,
    ): array {
        return $this->saveConversation->execute($user, $conversationId, 'assistant', $content, $toolCalls);
    }

    /**
     * Load conversation history for AI context.
     */
    public function loadConversation(User $user, string $conversationId): array
    {
        return $this->loadConversation->execute($user, $conversationId);
    }

    /**
     * Clear/delete a conversation.
     */
    public function clearConversation(User $user, string $conversationId): array
    {
        return $this->clearConversation->execute($user, $conversationId);
    }

    /**
     * Format messages for AI agent context.
     *
     * @param array $messages Array of message objects from LoadConversation
     *
     * @return array Array in format expected by Symfony AI
     */
    public function formatMessagesForAI(array $messages): array
    {
        $formatted = [];

        foreach ($messages as $msg) {
            $formatted[] = [
                'role' => $msg['role'],
                'content' => $msg['content'],
            ];
        }

        return $formatted;
    }
}
