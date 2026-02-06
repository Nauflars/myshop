<?php

namespace App\Application\UseCase\AI\Conversation;

use App\Domain\Entity\User;
use App\Domain\Repository\ConversationRepositoryInterface;

/**
 * ListUserConversations Use Case
 * 
 * Lists all conversations for the authenticated user, ordered by most recent.
 * Returns summary information without full message content.
 */
final class ListUserConversations
{
    public function __construct(
        private readonly ConversationRepositoryInterface $conversationRepository
    ) {
    }

    /**
     * @param User $user The authenticated user
     * @return array{success: bool, conversations: array, count: int, message: string}
     */
    public function execute(User $user): array
    {
        try {
            $conversations = $this->conversationRepository->findByUser($user);

            $summaries = [];
            foreach ($conversations as $conversation) {
                $lastMessage = $conversation->getLastMessage();
                
                $summaries[] = [
                    'id' => $conversation->getId(),
                    'title' => $conversation->getTitle(),
                    'messageCount' => $conversation->getMessageCount(),
                    'lastMessage' => $lastMessage ? [
                        'content' => mb_substr($lastMessage->getContent(), 0, 100),
                        'timestamp' => $lastMessage->getTimestamp()->format('Y-m-d H:i:s'),
                    ] : null,
                    'createdAt' => $conversation->getCreatedAt()->format('Y-m-d H:i:s'),
                    'updatedAt' => $conversation->getUpdatedAt()->format('Y-m-d H:i:s'),
                ];
            }

            return [
                'success' => true,
                'conversations' => $summaries,
                'count' => count($summaries),
                'message' => sprintf('Se encontraron %d conversación(es).', count($summaries)),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'conversations' => [],
                'count' => 0,
                'message' => 'Error al listar las conversaciones: ' . $e->getMessage(),
            ];
        }
    }
}
