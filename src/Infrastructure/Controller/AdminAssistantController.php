<?php

declare(strict_types=1);

namespace App\Infrastructure\Controller;

use App\Domain\Entity\User;
use App\Infrastructure\AI\Service\AdminConversationManager;
use Symfony\AI\Agent\AgentInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * AdminAssistantController - Admin Virtual Assistant chat interface
 * 
 * Part of spec-007: Admin Virtual Assistant
 * Provides AI-powered assistant exclusively for administrators
 */
#[Route('/admin/assistant')]
#[IsGranted('ROLE_ADMIN', message: 'Acceso denegado. Se requiere rol de administrador.')]
class AdminAssistantController extends AbstractController
{
    public function __construct(
        private readonly AdminConversationManager $conversationManager,
        private readonly Security $security,
        #[Autowire(service: 'ai.agent.adminAssistant')]
        private readonly AgentInterface $adminAgent
    ) {
    }

    /**
     * Render admin assistant chat interface
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
     * Handle admin chat message
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

            // Get or create conversation
            $conversation = $this->conversationManager->getOrCreateConversation($user, $sessionId);

            // Save admin message
            $this->conversationManager->saveAdminMessage($conversation, $userMessage);

            // Get conversation history as MessageBag
            $messageBag = $this->conversationManager->conversationToMessageBag($conversation);

            // Get AI response  
            $response = $this->adminAgent->call($messageBag);
            $assistantReply = $response->getContent();

            // Save assistant response
            $this->conversationManager->saveAssistantMessage($conversation, $assistantReply);

            return $this->json([
                'success' => true,
                'reply' => $assistantReply,
                'conversation_id' => $conversation->getId(),
                'message_count' => $conversation->getMessageCount(),
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Error al procesar el mensaje',
                'details' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get conversation history
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
     * Start a new conversation
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
     * Clear conversation context
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
}
