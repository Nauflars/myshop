<?php

namespace App\Application\UseCase\AI\Conversation;

use App\Domain\Entity\Conversation;
use App\Domain\Entity\ConversationMessage;
use App\Domain\Entity\User;
use App\Domain\Repository\ConversationRepositoryInterface;

/**
 * SaveConversation Use Case
 * 
 * Creates or updates a conversation with new messages.
 * If a conversation ID is provided, adds the message to that conversation.
 * Otherwise, creates a new conversation for the user.
 */
final class SaveConversation
{
    public function __construct(
        private readonly ConversationRepositoryInterface $conversationRepository
    ) {
    }

    /**
     * @param User $user The authenticated user
     * @param string|null $conversationId Existing conversation ID or null for new
     * @param string $role Message role (user, assistant, system)
     * @param string $content Message content
     * @param array|null $toolCalls Optional tool calls metadata
     * @return array{success: bool, conversationId: string, messageId: string, message: string}
     */
    public function execute(
        User $user,
        ?string $conversationId,
        string $role,
        string $content,
        ?array $toolCalls = null
    ): array {
        try {
            // Find existing conversation or create new one
            if ($conversationId !== null) {
                $conversation = $this->conversationRepository->findById($conversationId);
                
                // Verify ownership
                if ($conversation === null || $conversation->getUser()->getId() !== $user->getId()) {
                    // Create new conversation if not found or not owned
                    $conversation = new Conversation($user);
                }
            } else {
                $conversation = new Conversation($user);
            }

            // Create and add message
            $message = new ConversationMessage($conversation, $role, $content, $toolCalls);
            $conversation->addMessage($message);

            // Generate title from first user message if still default
            if ($conversation->getTitle() === 'Nueva conversación' && $role === 'user') {
                $conversation->setTitle($conversation->generateTitle());
            }

            // Persist
            $this->conversationRepository->save($conversation);

            return [
                'success' => true,
                'conversationId' => $conversation->getId(),
                'messageId' => $message->getId(),
                'message' => 'Mensaje guardado correctamente.',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'conversationId' => $conversationId ?? '',
                'messageId' => '',
                'message' => 'Error al guardar el mensaje: ' . $e->getMessage(),
            ];
        }
    }
}
