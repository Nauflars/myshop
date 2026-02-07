<?php

namespace App\Infrastructure\Controller;

use App\Application\Service\CustomerContextManager;
use App\Application\Service\UnansweredQuestionCapture;
use App\Domain\Entity\UnansweredQuestion;
use App\Domain\Entity\User;
use App\Infrastructure\AI\Service\ConversationManager;
use App\Infrastructure\AI\Service\RoleAwareAssistant;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * ChatbotController - Handles chat interactions with conversation persistence
 * 
 * Updated in spec-003 to persist conversations to database.
 * Updated in spec-009 to add conversational context and memory management.
 */
#[Route('/api/chat')]
class ChatbotController extends AbstractController
{
    public function __construct(
        private readonly ConversationManager $conversationManager,
        private readonly RoleAwareAssistant $roleAwareAssistant,
        private readonly Security $security,
        private readonly UnansweredQuestionCapture $unansweredQuestionCapture,
        private readonly CustomerContextManager $contextManager,
        private readonly AgentInterface $agent
    ) {
    }

    #[Route('', name: 'api_chatbot', methods: ['POST'])]
    public function chat(Request $request): JsonResponse
    {
        try {
            // Validate authentication
            $user = $this->security->getUser();
            if (!$user instanceof User) {
                return $this->json([
                    'error' => 'Usuario no autenticado',
                ], Response::HTTP_UNAUTHORIZED);
            }

            // Parse request
            $data = json_decode($request->getContent(), true);
            
            if (!isset($data['message']) || empty(trim($data['message']))) {
                return $this->json([
                    'error' => 'Message is required',
                ], Response::HTTP_BAD_REQUEST);
            }
            
            $userMessage = trim($data['message']);
            $conversationId = $data['conversationId'] ?? null;
            
            // Load conversation history if conversationId provided
            $conversationHistory = [];
            if ($conversationId !== null) {
                $loadResult = $this->conversationManager->loadConversation($user, $conversationId);
                if ($loadResult['success']) {
                    $conversationHistory = $this->conversationManager->formatMessagesForAI($loadResult['messages']);
                }
            }
            
            // Save user message to database
            $saveUserResult = $this->conversationManager->saveUserMessage($user, $conversationId, $userMessage);
            if (!$saveUserResult['success']) {
                return $this->json([
                    'error' => 'Error al guardar el mensaje',
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
            
            $conversationId = $saveUserResult['conversationId'];
            
            // Load or create customer context (spec-009)
            // Wrapped in try-catch to ensure Redis failures don't break chatbot
            $context = null;
            try {
                $userId = (string) $user->getId();
                $context = $this->contextManager->getOrCreateContext($userId);
                
                // Refresh TTL on interaction (spec-009 US3)
                $this->contextManager->refreshTtl($userId);
            } catch (\Exception $e) {
                error_log('Error loading customer context: ' . $e->getMessage());
                // Continue without context - chatbot will work in stateless mode
            }
            
            // Build message bag with conversation history for context
            $messages = [];
            foreach ($conversationHistory as $msg) {
                if ($msg['role'] === 'user') {
                    $messages[] = Message::ofUser($msg['content']);
                } elseif ($msg['role'] === 'assistant') {
                    $messages[] = Message::ofAssistant($msg['content']);
                }
            }
            // Add current user message
            $messages[] = Message::ofUser($userMessage);
            
            // Use Symfony AI Agent to process the message with context
            try {
                $messageBag = new MessageBag(...$messages);
                $result = $this->agent->call($messageBag);
                
                // Extract response content - handle both string and array responses
                $content = $result->getContent();
                $assistantResponse = is_array($content) ? json_encode($content) : (string) $content;
                
                // Update context after successful AI interaction (spec-009)
                if ($context !== null) {
                    $context->incrementTurnCount();
                }
                // Note: Tool execution context updates will be added in T017
            } catch (\Exception $e) {
                error_log('Symfony AI Agent Error: ' . $e->getMessage());
                error_log('Stack trace: ' . $e->getTraceAsString());
                
                // Capture as unanswered question for spec-006 (FR-001 to FR-008)
                try {
                    $this->unansweredQuestionCapture->capture(
                        questionText: $userMessage,
                        user: $user,
                        userRole: $user->getRoles()[0] ?? 'ROLE_CUSTOMER',
                        reasonCategory: UnansweredQuestion::REASON_TOOL_ERROR,
                        conversationId: $conversationId
                    );
                } catch (\Exception $captureException) {
                    error_log('Failed to capture unanswered question: ' . $captureException->getMessage());
                }
                
                // Use polite fallback message
                $assistantResponse = "Disculpa, estoy teniendo problemas para procesar tu solicitud. Por favor intenta de nuevo.";
            }
            
            // Save assistant response to database
            $saveAssistantResult = $this->conversationManager->saveAssistantMessage(
                $user,
                $conversationId,
                $assistantResponse
            );
            
            if (!$saveAssistantResult['success']) {
                // Log error but don't fail the request - user already got the response
                error_log('Error saving assistant message: ' . $saveAssistantResult['message']);
            }
            
            // Save updated context to Redis (spec-009)
            if ($context !== null) {
                try {
                    $this->contextManager->saveContext($context);
                } catch (\Exception $e) {
                    // Log error but don't fail the request - context is not critical
                    error_log('Error saving customer context: ' . $e->getMessage());
                }
            }
            
            return $this->json([
                'response' => $assistantResponse,
                'conversationId' => $conversationId,
                'role' => $this->roleAwareAssistant->getRoleDisplayName(),
            ]);
            
        } catch (\Throwable $e) {
            error_log('ChatbotController Error: ' . $e->getMessage());
            error_log('File: ' . $e->getFile() . ':' . $e->getLine());
            error_log('Trace: ' . $e->getTraceAsString());
            
            return $this->json([
                'error' => 'An error occurred while processing your request',
                'message' => $e->getMessage(),
                'file' => $e->getFile() . ':' . $e->getLine(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    #[Route('/history/{conversationId}', name: 'api_chatbot_history', methods: ['GET'])]
    public function getHistory(string $conversationId): JsonResponse
    {
        try {
            $user = $this->security->getUser();
            if (!$user instanceof User) {
                return $this->json([
                    'success' => false,
                    'error' => 'Usuario no autenticado',
                ], Response::HTTP_UNAUTHORIZED);
            }
            
            $result = $this->conversationManager->loadConversation($user, $conversationId);
            
            if ($result['success']) {
                return $this->json([
                    'success' => true,
                    'messages' => $result['messages'],
                    'conversation' => $result['conversation'],
                ]);
            }
            
            return $this->json([
                'success' => false,
                'error' => $result['message'],
                'messages' => [],
            ], Response::HTTP_NOT_FOUND);
            
        } catch (\Exception $e) {
            error_log('Get History Error: ' . $e->getMessage());
            return $this->json([
                'success' => false,
                'error' => 'Error al cargar el historial',
                'messages' => [],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    #[Route('/clear', name: 'api_chatbot_clear', methods: ['POST'])]
    public function clearChat(Request $request): JsonResponse
    {
        try {
            $user = $this->security->getUser();
            if (!$user instanceof User) {
                return $this->json([
                    'error' => 'Usuario no autenticado',
                ], Response::HTTP_UNAUTHORIZED);
            }
            
            $data = json_decode($request->getContent(), true);
            $conversationId = $data['conversationId'] ?? null;
            
            if (!$conversationId) {
                return $this->json([
                    'error' => 'Conversation ID is required',
                ], Response::HTTP_BAD_REQUEST);
            }
            
            $result = $this->conversationManager->clearConversation($user, $conversationId);
            
            if ($result['success']) {
                return $this->json([
                    'success' => true,
                    'message' => 'Conversación eliminada correctamente.',
                ]);
            }
            
            return $this->json([
                'error' => $result['message'],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
            
        } catch (\Exception $e) {
            error_log('Clear Chat Error: ' . $e->getMessage());
            return $this->json([
                'error' => 'Error al limpiar la conversación',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * Reset customer context (spec-009 US4)
     * Clears context from Redis, allowing fresh start
     */
    #[Route('/reset-context', name: 'api_chatbot_reset_context', methods: ['POST'])]
    public function resetContext(): JsonResponse
    {
        try {
            $user = $this->security->getUser();
            if (!$user instanceof User) {
                return $this->json([
                    'error' => 'Usuario no autenticado',
                ], Response::HTTP_UNAUTHORIZED);
            }
            
            $userId = (string) $user->getId();
            $deleted = $this->contextManager->deleteContext($userId);
            
            return $this->json([
                'success' => true,
                'message' => $deleted 
                    ? 'Context reset successful. Starting fresh conversation.'
                    : 'No context to reset. Ready for new conversation.',
            ]);
            
        } catch (\Exception $e) {
            error_log('Reset Context Error: ' . $e->getMessage());
            return $this->json([
                'error' => 'Error al resetear el contexto',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
