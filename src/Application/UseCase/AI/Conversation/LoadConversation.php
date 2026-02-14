<?php

namespace App\Application\UseCase\AI\Conversation;

use App\Domain\Entity\User;
use App\Domain\Repository\ConversationRepositoryInterface;

/**
 * LoadConversation Use Case.
 *
 * Loads a conversation with all messages for the authenticated user.
 * Returns formatted message history for AI context.
 */
final class LoadConversation
{
    public function __construct(
        private readonly ConversationRepositoryInterface $conversationRepository,
    ) {
    }

    /**
     * @param User   $user           The authenticated user
     * @param string $conversationId The conversation to load
     *
     * @return array{success: bool, conversation: array|null, messages: array, message: string}
     */
    public function execute(User $user, string $conversationId): array
    {
        try {
            $conversation = $this->conversationRepository->findById($conversationId);

            // Verify existence and ownership
            if (null === $conversation) {
                return [
                    'success' => false,
                    'conversation' => null,
                    'messages' => [],
                    'message' => 'Conversación no encontrada.',
                ];
            }

            if ($conversation->getUser()->getId() !== $user->getId()) {
                return [
                    'success' => false,
                    'conversation' => null,
                    'messages' => [],
                    'message' => 'No tienes acceso a esta conversación.',
                ];
            }

            // Format messages for AI context
            $messages = [];
            foreach ($conversation->getMessages() as $msg) {
                $messages[] = [
                    'id' => $msg->getId(),
                    'role' => $msg->getRole(),
                    'content' => $msg->getContent(),
                    'toolCalls' => $msg->getToolCalls(),
                    'timestamp' => $msg->getTimestamp()->format('Y-m-d H:i:s'),
                ];
            }

            return [
                'success' => true,
                'conversation' => [
                    'id' => $conversation->getId(),
                    'title' => $conversation->getTitle(),
                    'createdAt' => $conversation->getCreatedAt()->format('Y-m-d H:i:s'),
                    'updatedAt' => $conversation->getUpdatedAt()->format('Y-m-d H:i:s'),
                    'messageCount' => $conversation->getMessageCount(),
                ],
                'messages' => $messages,
                'message' => 'Conversación cargada correctamente.',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'conversation' => null,
                'messages' => [],
                'message' => 'Error al cargar la conversación: '.$e->getMessage(),
            ];
        }
    }
}
