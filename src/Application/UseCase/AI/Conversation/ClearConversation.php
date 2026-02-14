<?php

namespace App\Application\UseCase\AI\Conversation;

use App\Domain\Entity\User;
use App\Domain\Repository\ConversationRepositoryInterface;

/**
 * ClearConversation Use Case.
 *
 * Deletes a conversation and all its messages for the authenticated user.
 * Used when user clicks "Clear Chat" button.
 */
final class ClearConversation
{
    public function __construct(
        private readonly ConversationRepositoryInterface $conversationRepository,
    ) {
    }

    /**
     * @param User   $user           The authenticated user
     * @param string $conversationId The conversation to delete
     *
     * @return array{success: bool, message: string}
     */
    public function execute(User $user, string $conversationId): array
    {
        try {
            $conversation = $this->conversationRepository->findById($conversationId);

            // Verify existence and ownership
            if (null === $conversation) {
                return [
                    'success' => false,
                    'message' => 'Conversación no encontrada.',
                ];
            }

            if ($conversation->getUser()->getId() !== $user->getId()) {
                return [
                    'success' => false,
                    'message' => 'No tienes acceso a esta conversación.',
                ];
            }

            // Delete conversation and all messages (cascade)
            $this->conversationRepository->delete($conversation);

            return [
                'success' => true,
                'message' => 'Conversación eliminada correctamente.',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al eliminar la conversación: '.$e->getMessage(),
            ];
        }
    }
}
