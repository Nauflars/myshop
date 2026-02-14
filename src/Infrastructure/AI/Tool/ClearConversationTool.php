<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Tool;

use App\Application\UseCase\AI\Conversation\ClearConversation;
use App\Domain\Entity\User;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * ClearConversationTool - AI Tool for clearing chat history.
 *
 * Allows the authenticated user to clear their current conversation history.
 * This is typically triggered by a "Clear Chat" button in the UI or by direct user request.
 */
#[AsTool(
    'ClearConversation',
    'Clear the current user\'s conversation history. Use this tool when the user explicitly asks to delete or clear the chat.'
)]
final class ClearConversationTool
{
    public function __construct(
        private readonly ClearConversation $clearConversation,
        private readonly Security $security,
    ) {
    }

    /**
     * @param string $conversationId The conversation to clear
     *
     * @return array{success: bool, message: string}
     */
    public function __invoke(string $conversationId): array
    {
        try {
            $user = $this->security->getUser();

            if (!$user instanceof User) {
                return [
                    'success' => false,
                    'message' => 'You must log in to clear conversations.',
                ];
            }

            $result = $this->clearConversation->execute($user, $conversationId);

            if ($result['success']) {
                return [
                    'success' => true,
                    'message' => 'I\'ve cleared our conversation history. How can I help you now?',
                ];
            }

            return $result;
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Could not clear the conversation. Please try again.',
            ];
        }
    }
}
