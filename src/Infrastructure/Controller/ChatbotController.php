<?php

namespace App\Infrastructure\Controller;

use App\Application\Service\UnansweredQuestionCapture;
use App\Application\Service\UnifiedCustomerContextManager;
use App\Domain\Entity\UnansweredQuestion;
use App\Domain\Entity\User;
use App\Infrastructure\AI\Service\ConversationManager;
use App\Infrastructure\AI\Service\RoleAwareAssistant;
use Psr\Log\LoggerInterface;
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
 * ChatbotController - Handles chat interactions with conversation persistence.
 *
 * Updated in spec-003 to persist conversations to database.
 * Updated in spec-009 to add conversational context and memory management.
 * Updated in spec-012 to use unified conversation architecture with Redis history.
 */
#[Route('/api/chat')]
class ChatbotController extends AbstractController
{
    public function __construct(
        private readonly ConversationManager $conversationManager,
        private readonly RoleAwareAssistant $roleAwareAssistant,
        private readonly Security $security,
        private readonly UnansweredQuestionCapture $unansweredQuestionCapture,
        private readonly UnifiedCustomerContextManager $unifiedContextManager,
        private readonly AgentInterface $agent,
        private readonly LoggerInterface $aiAgentLogger,
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
                    'error' => 'User not authenticated',
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

            // Save user message to database first (MySQL)
            $saveUserResult = $this->conversationManager->saveUserMessage($user, $conversationId, $userMessage);
            if (!$saveUserResult['success']) {
                return $this->json([
                    'error' => 'Error saving message',
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            $conversationId = $saveUserResult['conversationId'];
            $userId = (string) $user->getId();

            // Load or create unified conversation context (spec-012)
            // This includes both state and last 10 messages from Redis
            $unifiedConversation = null;
            try {
                $unifiedConversation = $this->unifiedContextManager->getOrCreateConversation($userId, $conversationId);

                // Add current user message to Redis history
                $this->unifiedContextManager->addMessage(
                    $userId,
                    $unifiedConversation['conversationId'],
                    'user',
                    $userMessage
                );
            } catch (\Exception $e) {
                $this->aiAgentLogger->warning('Error loading unified conversation', [
                    'error' => $e->getMessage(),
                    'user_id' => $userId,
                    'conversation_id' => $conversationId,
                ]);
                // Continue without Redis context - chatbot will work in stateless mode
            }

            // Build message bag with context and history from Redis
            $messages = [];
            if (null !== $unifiedConversation) {
                // Use buildMessageBagContext which includes system message with state + history
                $contextMessages = $this->unifiedContextManager->buildMessageBagContext(
                    $userId,
                    $unifiedConversation['conversationId']
                );

                foreach ($contextMessages as $msg) {
                    if ('user' === $msg['role']) {
                        $messages[] = Message::ofUser($msg['content']);
                    } elseif ('assistant' === $msg['role']) {
                        $messages[] = Message::ofAssistant($msg['content']);
                    }
                    // Note: System messages are handled by the AI Agent configuration
                    // Symfony AI Platform doesn't support Message::ofSystem() in this version
                }
            }

            // Add current user message to MessageBag
            $messages[] = Message::ofUser($userMessage);

            // Use Symfony AI Agent to process the message with context
            try {
                $this->aiAgentLogger->info('🤖 AI AGENT CALL START', [
                    'user_message' => $userMessage,
                    'conversation_id' => $conversationId,
                    'user_id' => $userId,
                    'messages_in_context' => count($messages),
                    'user_roles' => $user->getRoles(),
                    'agent_class' => get_class($this->agent),
                ]);

                $messageBag = new MessageBag(...$messages);
                $this->aiAgentLogger->debug('MessageBag created', ['message_count' => count($messageBag)]);

                $result = $this->agent->call($messageBag);

                $this->aiAgentLogger->info('AI Agent returned result', [
                    'result_class' => get_class($result),
                ]);

                // Log tool calls if available
                if (method_exists($result, 'getToolCalls')) {
                    $toolCalls = $result->getToolCalls();
                    $this->aiAgentLogger->info('🔧 Tool Calls Made', [
                        'tool_calls' => $toolCalls,
                    ]);
                }

                // Log metadata if available
                if (method_exists($result, 'getMetadata')) {
                    $metadata = $result->getMetadata();
                    $this->aiAgentLogger->debug('Result Metadata', [
                        'metadata' => $metadata,
                    ]);
                }

                if (method_exists($result, 'getMessage')) {
                    try {
                        $message = $result->getMessage();
                        $this->aiAgentLogger->debug('Result Message', [
                            'message_class' => get_class($message),
                        ]);
                    } catch (\Exception $e) {
                        $this->aiAgentLogger->warning('Could not get message from result', [
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                // Extract response content - handle both string and array responses
                $content = $result->getContent();

                $this->aiAgentLogger->info('🤖 AI AGENT CALL END', [
                    'response_type' => gettype($content),
                    'response_content' => is_string($content) ? $content : json_encode($content),
                ]);

                // Handle different response types from AI Agent
                if (is_string($content)) {
                    // Check if string is actually "[{},{}]" pattern
                    if (preg_match('/^\[\s*\{\s*\}\s*(?:,\s*\{\s*\}\s*)*\]$/', trim($content))) {
                        $assistantResponse = "I've processed your request. Is there anything else I can help you with?";
                    } else {
                        $assistantResponse = $content;
                    }
                } elseif (is_array($content)) {
                    // If array is empty or contains empty objects, provide fallback
                    if (empty($content) || $this->isEmptyArrayResponse($content)) {
                        $assistantResponse = "I've processed your request. Is there anything else I can help you with?";
                    } else {
                        // Try to extract a meaningful message from the array
                        $assistantResponse = $this->extractMessageFromArray($content);

                        // If extraction returned JSON of empty objects, use fallback
                        if (preg_match('/^\[\s*\{\s*\}\s*(?:,\s*\{\s*\}\s*)*\]$/', trim($assistantResponse))) {
                            $assistantResponse = "I've processed your request. Is there anything else I can help you with?";
                        }
                    }
                } else {
                    // Fallback for unexpected types
                    $assistantResponse = (string) $content;
                }

                // Update context after successful AI interaction (spec-012)
                if (null !== $unifiedConversation) {
                    try {
                        // Add assistant response to Redis history
                        $this->unifiedContextManager->addMessage(
                            $userId,
                            $unifiedConversation['conversationId'],
                            'assistant',
                            $assistantResponse
                        );

                        // Update state with turn count
                        $state = $this->unifiedContextManager->getState($userId, $unifiedConversation['conversationId']);
                        $state['turn_count'] = ($state['turn_count'] ?? 0) + 1;
                        $this->unifiedContextManager->updateState($userId, $unifiedConversation['conversationId'], $state);
                    } catch (\Exception $e) {
                        $this->aiAgentLogger->warning('Error updating unified conversation', [
                            'error' => $e->getMessage(),
                            'user_id' => $userId,
                            'conversation_id' => $unifiedConversation['conversationId'],
                        ]);
                    }
                }
                // Note: Tool execution context updates will be added in T017
            } catch (\Exception $e) {
                $this->aiAgentLogger->error('Symfony AI Agent Error', [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]);

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
                    $this->aiAgentLogger->error('Failed to capture unanswered question', [
                        'error' => $captureException->getMessage(),
                    ]);
                }

                // Use polite fallback message
                $assistantResponse = 'Disculpa, estoy teniendo problemas para procesar tu solicitud. Por favor intenta de nuevo.';
            }

            // Save assistant response to database
            $saveAssistantResult = $this->conversationManager->saveAssistantMessage(
                $user,
                $conversationId,
                $assistantResponse
            );

            if (!$saveAssistantResult['success']) {
                // Log error but don't fail the request - user already got the response
                $this->aiAgentLogger->warning('Error saving assistant message', [
                    'message' => $saveAssistantResult['message'],
                ]);
            }

            return $this->json([
                'response' => $assistantResponse,
                'conversationId' => $conversationId,
                'role' => $this->roleAwareAssistant->getRoleDisplayName(),
            ]);
        } catch (\Throwable $e) {
            $this->aiAgentLogger->critical('ChatbotController Error', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->json([
                'error' => 'An error occurred while processing your request',
                'message' => $e->getMessage(),
                'file' => $e->getFile().':'.$e->getLine(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Check if array response contains only empty objects.
     */
    private function isEmptyArrayResponse(array $content): bool
    {
        if (empty($content)) {
            return true;
        }

        foreach ($content as $item) {
            // If item is not an array or object, it has content
            if (!is_array($item) && !is_object($item)) {
                return false;
            }

            // Check if array has content
            if (is_array($item) && !empty($item)) {
                return false;
            }

            // Check if object has properties
            if (is_object($item)) {
                $vars = get_object_vars($item);
                if (!empty($vars)) {
                    return false;
                }
                // Also check for stdClass specifically
                if ($item instanceof \stdClass && [] !== (array) $item) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Extract meaningful message from array response.
     */
    private function extractMessageFromArray(array $content): string
    {
        // Try to find 'message' key in response
        if (isset($content['message']) && is_string($content['message'])) {
            return $content['message'];
        }

        // Try to find first string value in array
        foreach ($content as $value) {
            if (is_string($value) && !empty(trim($value))) {
                return $value;
            }
            if (is_array($value) && isset($value['message'])) {
                return $value['message'];
            }
        }

        // If nothing meaningful found, check if it's empty objects before encoding
        if ($this->isEmptyArrayResponse($content)) {
            return "I've processed your request. Is there anything else I can help you with?";
        }

        // Last resort: return JSON representation
        return json_encode($content);
    }

    #[Route('/history/{conversationId}', name: 'api_chatbot_history', methods: ['GET'])]
    public function getHistory(string $conversationId): JsonResponse
    {
        try {
            $user = $this->security->getUser();
            if (!$user instanceof User) {
                return $this->json([
                    'success' => false,
                    'error' => 'User not authenticated',
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
            $this->aiAgentLogger->error('Get History Error', ['error' => $e->getMessage()]);

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
                    'error' => 'User not authenticated',
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
                    'message' => 'Conversation deleted successfully.',
                ]);
            }

            return $this->json([
                'error' => $result['message'],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        } catch (\Exception $e) {
            $this->aiAgentLogger->error('Clear Chat Error', ['error' => $e->getMessage()]);

            return $this->json([
                'error' => 'Error clearing conversation',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Reset customer context (spec-009 US4)
     * Clears context from Redis, allowing fresh start.
     */
    #[Route('/reset-context', name: 'api_chatbot_reset_context', methods: ['POST'])]
    public function resetContext(Request $request): JsonResponse
    {
        try {
            $user = $this->security->getUser();
            if (!$user instanceof User) {
                return $this->json([
                    'error' => 'User not authenticated',
                ], Response::HTTP_UNAUTHORIZED);
            }

            $data = json_decode($request->getContent(), true);
            $conversationId = $data['conversationId'] ?? null;

            if (!$conversationId) {
                return $this->json([
                    'error' => 'conversationId is required',
                ], Response::HTTP_BAD_REQUEST);
            }

            $userId = (string) $user->getId();
            $deleted = $this->unifiedContextManager->deleteConversation($userId, $conversationId);

            return $this->json([
                'success' => true,
                'message' => $deleted
                    ? 'Context reset successful. Starting fresh conversation.'
                    : 'No context to reset. Ready for new conversation.',
            ]);
        } catch (\Exception $e) {
            $this->aiAgentLogger->error('Reset Context Error', ['error' => $e->getMessage()]);

            return $this->json([
                'error' => 'Error resetting context',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
