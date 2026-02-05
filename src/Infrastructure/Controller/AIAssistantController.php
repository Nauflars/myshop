<?php

declare(strict_types=1);

namespace App\Infrastructure\Controller;

use App\Infrastructure\AI\Service\ConversationManager;
use App\Infrastructure\AI\Service\RoleAwareAssistant;
use Symfony\AI\AgentInterface;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * AIAssistantController - Handles AI-powered conversational shopping assistant endpoints
 * 
 * This controller provides the REST API for AI chat interactions,
 * integrating Symfony AI Agent with tools, conversation memory, and role-based access.
 * 
 * Architecture: Infrastructure layer (HTTP/API adapter)
 * DDD Role: Presentation layer - marshals requests to AI services
 */
#[Route('/api/ai', name: 'api_ai_')]
class AIAssistantController extends AbstractController
{
    public function __construct(
        private readonly ConversationManager $conversationManager,
        private readonly RoleAwareAssistant $roleAwareAssistant,
        #[Autowire(service: 'ai.agent.local_ollama')]
        private readonly AgentInterface $agent
    ) {
    }
    
    /**
     * POST /api/ai/chat - Main chat endpoint for AI conversations
     * 
     * Accepts user messages and returns AI assistant responses.
     * Maintains conversation context across multiple exchanges.
     * 
     * Request body: {"message": "Show me products under $50"}
     * Response: {"response": "...", "role": "customer", "conversationId": "..."}
     */
    #[Route('/chat', name: 'chat', methods: ['POST'])]
    public function chat(Request $request): JsonResponse
    {
        try {
            // Parse request
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
                // Create MessageBag with user message
                $messageBag = new MessageBag();
                $messageBag->add(new UserMessage($userMessage));
                
                // Call agent with MessageBag
                $result = $this->agent->call($messageBag);
                $assistantResponse = $result->content;
            } catch (\Exception $e) {
                error_log('Symfony AI Agent Error: ' . $e->getMessage());
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
                'conversationId' => $this->conversationManager->getConversationId(),
                'messageCount' => $this->conversationManager->getMessageCount(),
            ]);
            
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'An error occurred processing your request',
                'details' => $this->getParameter('kernel.debug') ? $e->getMessage() : null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * GET /api/ai/history - Retrieve conversation history
     * 
     * Returns the current conversation context for debugging or review.
     */
    #[Route('/history', name: 'history', methods: ['GET'])]
    public function history(): JsonResponse
    {
        $history = $this->conversationManager->getFormattedHistory();
        
        return $this->json([
            'history' => $history,
            'messageCount' => $this->conversationManager->getMessageCount(),
            'conversationId' => $this->conversationManager->getConversationId(),
        ]);
    }
    
    /**
     * DELETE /api/ai/history - Clear conversation history
     * 
     * Resets the conversation context, starting fresh.
     */
    #[Route('/history', name: 'history_clear', methods: ['DELETE'])]
    public function clearHistory(): JsonResponse
    {
        $this->conversationManager->clearHistory();
        
        return $this->json([
            'message' => 'Conversation history cleared',
        ]);
    }
    
    /**
     * GET /api/ai/status - Get AI assistant status and capabilities
     * 
     * Returns information about available tools, user role, and system status.
     */
    #[Route('/status', name: 'status', methods: ['GET'])]
    public function status(): JsonResponse
    {
        return $this->json([
            'status' => 'operational',
            'user' => [
                'role' => $this->roleAwareAssistant->getRoleDisplayName(),
                'authenticated' => $this->roleAwareAssistant->isAuthenticated(),
            ],
            'conversation' => [
                'hasContext' => $this->conversationManager->hasContext(),
                'messageCount' => $this->conversationManager->getMessageCount(),
            ],
            'availableTools' => [
                'GetProductsName',
                'GetProductsNameByMaxPrice',
                'GetPriceByProductId',
                'GetProductImagesByProductId',
                'AddToCart',
                'GetCartTotal',
                'CheckoutOrder',
            ],
        ]);
    }
}
