<?php

declare(strict_types=1);

namespace App\Infrastructure\Controller;

use App\Application\Service\UnifiedAdminContextManager;
use App\Domain\Entity\User;
use App\Infrastructure\AI\Service\AdminConversationManager;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * AdminAssistantController - Admin Virtual Assistant chat interface.
 *
 * Part of spec-007: Admin Virtual Assistant
 * Updated in spec-012 to use unified conversation architecture
 * Provides AI-powered assistant exclusively for administrators
 */
#[Route('/admin/assistant')]
#[IsGranted('ROLE_ADMIN', message: 'Acceso denegado. Se requiere rol de administrador.')]
class AdminAssistantController extends AbstractController
{
    public function __construct(
        private readonly AdminConversationManager $conversationManager,
        private readonly Security $security,
        private readonly AgentInterface $adminAgent,
        private readonly UnifiedAdminContextManager $unifiedContextManager,
    ) {
    }

    /**
     * Render admin assistant chat interface.
     */
    #[Route('', name: 'admin_assistant', methods: ['GET'])]
    public function index(): Response
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Usuario no autenticado');
        }

        // Get or create active conversation
        $conversation = $this->conversationManager->getOrCreateConversation($user);

        return $this->render('admin/assistant/index.html.twig', [
            'pageTitle' => 'Asistente Virtual',
            'conversation' => $conversation,
            'messageCount' => $conversation->getMessageCount(),
        ]);
    }

    /**
     * Handle admin chat message.
     */
    #[Route('/chat', name: 'api_admin_assistant_chat', methods: ['POST'])]
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

            // Verify ADMIN role explicitly
            if (!in_array('ROLE_ADMIN', $user->getRoles(), true)) {
                return $this->json([
                    'error' => 'Acceso denegado. Se requiere rol de administrador.',
                ], Response::HTTP_FORBIDDEN);
            }

            // Parse request
            $data = json_decode($request->getContent(), true);
            if (!isset($data['message']) || empty(trim($data['message']))) {
                return $this->json([
                    'error' => 'Mensaje vacío',
                ], Response::HTTP_BAD_REQUEST);
            }

            $userMessage = trim($data['message']);
            $sessionId = $data['session_id'] ?? null;

            // Get or create conversation (MySQL)
            $conversation = $this->conversationManager->getOrCreateConversation($user, $sessionId);

            // Save admin message to MySQL
            $this->conversationManager->saveAdminMessage($conversation, $userMessage);

            // Load or create unified conversation context (spec-012)
            $adminId = (string) $user->getId();
            $conversationId = (string) $conversation->getId();
            $unifiedConversation = null;

            try {
                $unifiedConversation = $this->unifiedContextManager->getOrCreateConversation($adminId, $conversationId);

                // Add current admin message to Redis history
                $this->unifiedContextManager->addMessage(
                    $adminId,
                    $unifiedConversation['conversationId'],
                    'user',
                    $userMessage
                );
            } catch (\Exception $e) {
                error_log('Error loading unified admin conversation: '.$e->getMessage());
            }

            // Build message bag with context and history from Redis
            $messages = [];
            if (null !== $unifiedConversation) {
                $contextMessages = $this->unifiedContextManager->buildMessageBagContext(
                    $adminId,
                    $unifiedConversation['conversationId']
                );

                foreach ($contextMessages as $msg) {
                    if ('user' === $msg['role']) {
                        $messages[] = Message::ofUser($msg['content']);
                    } elseif ('assistant' === $msg['role']) {
                        $messages[] = Message::ofAssistant($msg['content']);
                    }
                    // System messages are included in the conversation history context
                }
            }

            // Add current user message to MessageBag
            $messages[] = Message::ofUser($userMessage);

            // Get AI response
            $messageBag = new MessageBag(...$messages);
            $response = $this->adminAgent->call($messageBag);
            $content = $response->getContent();

            // Handle different response types from AI Agent
            if (is_string($content)) {
                $assistantReply = $content;
            } elseif (is_array($content)) {
                // If array is empty or contains empty objects, provide fallback
                if (empty($content) || $this->isEmptyArrayResponse($content)) {
                    $assistantReply = "I've processed your request. Is there anything else I can help you with?";
                } else {
                    // Try to extract a meaningful message from the array
                    $assistantReply = $this->extractMessageFromArray($content);
                }
            } else {
                $assistantReply = (string) $content;
            }

            // Update context after AI interaction (spec-012)
            if (null !== $unifiedConversation) {
                try {
                    // Add assistant response to Redis history
                    $this->unifiedContextManager->addMessage(
                        $adminId,
                        $unifiedConversation['conversationId'],
                        'assistant',
                        $assistantReply
                    );

                    // Update state with turn count
                    $state = $this->unifiedContextManager->getState($adminId, $unifiedConversation['conversationId']);
                    $state['turn_count'] = ($state['turn_count'] ?? 0) + 1;
                    $this->unifiedContextManager->updateState($adminId, $unifiedConversation['conversationId'], $state);
                } catch (\Exception $e) {
                    error_log('Error updating unified admin conversation: '.$e->getMessage());
                }
            }

            // Save assistant response to MySQL
            $this->conversationManager->saveAssistantMessage($conversation, $assistantReply);

            return $this->json([
                'success' => true,
                'reply' => $assistantReply,
                'conversation_id' => $conversation->getId(),
                'message_count' => $conversation->getMessageCount(),
            ]);
        } catch (\Throwable $e) {
            error_log('AdminAssistantController Error: '.$e->getMessage());
            error_log('File: '.$e->getFile().':'.$e->getLine());
            error_log('Trace: '.$e->getTraceAsString());

            return $this->json([
                'success' => false,
                'error' => 'Error al procesar el mensaje',
                'details' => $e->getMessage(),
                'file' => $e->getFile().':'.$e->getLine(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get conversation history.
     */
    #[Route('/history', name: 'api_admin_assistant_history', methods: ['GET'])]
    public function history(): JsonResponse
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            return $this->json([
                'error' => 'Usuario no autenticado',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $conversation = $this->conversationManager->getOrCreateConversation($user);

        $messages = [];
        foreach ($conversation->getMessages() as $message) {
            $messages[] = [
                'id' => $message->getId(),
                'sender' => $message->getSender(),
                'text' => $message->getMessageText(),
                'sent_at' => $message->getSentAt()->format('Y-m-d H:i:s'),
                'has_tools' => $message->hasToolInvocations(),
                'has_error' => $message->hasError(),
            ];
        }

        return $this->json([
            'success' => true,
            'conversation_id' => $conversation->getId(),
            'messages' => $messages,
            'context' => $conversation->getContextState(),
        ]);
    }

    /**
     * Start a new conversation.
     */
    #[Route('/new', name: 'api_admin_assistant_new', methods: ['POST'])]
    public function newConversation(): JsonResponse
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            return $this->json([
                'error' => 'Usuario no autenticado',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $conversation = $this->conversationManager->startNewConversation($user);

        return $this->json([
            'success' => true,
            'conversation_id' => $conversation->getId(),
            'message' => 'Nueva conversación iniciada',
        ]);
    }

    /**
     * Clear conversation context.
     */
    #[Route('/clear-context', name: 'api_admin_assistant_clear_context', methods: ['POST'])]
    public function clearContext(): JsonResponse
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            return $this->json([
                'error' => 'Usuario no autenticado',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $conversation = $this->conversationManager->getOrCreateConversation($user);
        $this->conversationManager->clearContext($conversation);

        return $this->json([
            'success' => true,
            'message' => 'Contexto limpiado',
        ]);
    }

    /**
     * Check if array response contains only empty objects.
     */
    private function isEmptyArrayResponse(array $content): bool
    {
        foreach ($content as $item) {
            if (!is_array($item) && !is_object($item)) {
                return false;
            }
            if (is_array($item) && !empty($item)) {
                return false;
            }
            if (is_object($item)) {
                $vars = get_object_vars($item);
                if (!empty($vars)) {
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

        // If nothing found, return JSON representation
        return json_encode($content);
    }
}
