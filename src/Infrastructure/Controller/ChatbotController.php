<?php

namespace App\Infrastructure\Controller;

use App\Infrastructure\AI\Service\ConversationManager;
use App\Infrastructure\AI\Service\RoleAwareAssistant;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * ChatbotController - Legacy endpoint that forwards to AIAssistantController
 * Kept for backwards compatibility with existing chatbot widget
 */
#[Route('/api/chat')]
class ChatbotController extends AbstractController
{
    public function __construct(
        private readonly ConversationManager $conversationManager,
        private readonly RoleAwareAssistant $roleAwareAssistant,
        #[Autowire(service: 'ai.agent.openAiAgent')]
        private readonly AgentInterface $agent
    ) {
    }

    #[Route('', name: 'api_chatbot', methods: ['POST'])]
    public function chat(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!isset($data['message']) || empty(trim($data['message']))) {
                return $this->json([
                    'error' => 'Message is required',
                ], Response::HTTP_BAD_REQUEST);
            }
            
            $userMessage = trim($data['message']);
            
            // Get user context
            $userRole = $this->roleAwareAssistant->getCurrentUserRole();
            $userId = $this->roleAwareAssistant->getCurrentUserId();
            
            // Add user message to conversation history
            $this->conversationManager->addMessage('user', $userMessage, [
                'role' => $userRole,
                'userId' => $userId,
            ]);
            
            // Use Symfony AI Agent to process the message
            try {
                $messageBag = new MessageBag(
                    Message::ofUser($userMessage)
                );
                
                $result = $this->agent->call($messageBag);
                $assistantResponse = $result->getContent();
            } catch (\Exception $e) {
                error_log('Symfony AI Agent Error: ' . $e->getMessage());
                error_log('Stack trace: ' . $e->getTraceAsString());
                
                // Return user-friendly error
                $assistantResponse = "I apologize, but I'm having trouble processing your request right now. Please try again later.";
            }
            
            // Add assistant response to history
            $this->conversationManager->addMessage('assistant', $assistantResponse, [
                'toolsUsed' => [],
                'tokensUsed' => 0,
            ]);
            
            return $this->json([
                'response' => $assistantResponse,
                'role' => $this->roleAwareAssistant->getRoleDisplayName(),
            ]);
            
        } catch (\Exception $e) {
            error_log('ChatbotController Error: ' . $e->getMessage());
            return $this->json([
                'error' => 'An error occurred while processing your request',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
