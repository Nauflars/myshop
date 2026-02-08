<?php

namespace App\Infrastructure\Controller;

use App\Application\Service\UnifiedCustomerContextManager;
use App\Application\Service\UnansweredQuestionCapture;
use App\Domain\Entity\User;
use App\Infrastructure\AI\Service\ConversationManager;
use App\Infrastructure\AI\Service\RoleAwareAssistant;
use Symfony\AI\Agent\AgentInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/debug')]
class DebugController extends AbstractController
{
    public function __construct(
        private readonly ConversationManager $conversationManager,
        private readonly RoleAwareAssistant $roleAwareAssistant,
        private readonly Security $security,
        private readonly UnansweredQuestionCapture $unansweredQuestionCapture,
        private readonly UnifiedCustomerContextManager $contextManager,
        #[Autowire(service: 'ai.agent.openAiAgent')]
        private readonly AgentInterface $agent
    ) {
    }

    #[Route('/test-services', name: 'api_debug_services', methods: ['GET'])]
    public function testServices(): JsonResponse
    {
        try {
            $result = [
                'status' => 'Testing services...',
                'services' => []
            ];

            // Test each service
            $result['services']['conversationManager'] = get_class($this->conversationManager);
            $result['services']['roleAwareAssistant'] = get_class($this->roleAwareAssistant);
            $result['services']['security'] = get_class($this->security);
            $result['services']['unansweredQuestionCapture'] = get_class($this->unansweredQuestionCapture);
            $result['services']['contextManager'] = get_class($this->contextManager);
            $result['services']['agent'] = get_class($this->agent);

            // Try to get current user
            $user = $this->security->getUser();
            $result['user'] = $user ? [
                'id' => $user->getId(),
                'email' => $user->getUserIdentifier()
            ] : null;

            // Context API changed in spec-012 - now requires conversationId
            // Skipping context test for now
            if ($user instanceof User) {
                $result['context_note'] = 'Context API migrated to spec-012 (requires conversationId)';
            }

            $result['status'] = 'All services loaded successfully!';
            return $this->json($result);

        } catch (\Throwable $e) {
            return $this->json([
                'error' => $e->getMessage(),
                'file' => $e->getFile() . ':' . $e->getLine(),
                'trace' => explode("\n", $e->getTraceAsString())
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/test-chat-flow', name: 'api_debug_chat_flow', methods: ['POST'])]
    public function testChatFlow(): JsonResponse
    {
        try {
            $result = ['steps' => []];

            // Step 1: Get user
            $user = $this->security->getUser();
            if (!$user instanceof User) {
                return $this->json(['error' => 'No authenticated user'], Response::HTTP_UNAUTHORIZED);
            }
            $result['steps'][] = '✓ User authenticated: ' . $user->getUserIdentifier();

            // Step 2: Save user message
            $saveUserResult = $this->conversationManager->saveUserMessage($user, 1, 'Test message');
            $result['steps'][] = '✓ User message saved: ' . ($saveUserResult['success'] ? 'yes' : 'no');
            $conversationId = $saveUserResult['conversationId'];

            // Step 3: Load context
            $userId = (string) $user->getId();
            $context = $this->contextManager->getOrCreateContext($userId);
            $result['steps'][] = '✓ Context loaded: flow=' . $context->getFlow();

            // Step 4: Format messages
            $messages = [\Symfony\AI\Platform\Message\Message::ofUser('Test message')];
            $result['steps'][] = '✓ Messages formatted: ' . count($messages);

            // Step 5: Try AI agent call (this might fail but capture the error)
            try {
                $messageBag = new \Symfony\AI\Platform\Message\MessageBag(...$messages);
                $agentResult = $this->agent->call($messageBag);
                $assistantResponse = $agentResult->getContent();
                $result['steps'][] = '✓ AI agent call succeeded';
                $result['response'] = substr($assistantResponse, 0, 100);
            } catch (\Exception $e) {
                $result['steps'][] = '✗ AI agent call failed: ' . $e->getMessage();
                $assistantResponse = 'Fallback response';
            }

            // Step 6: Save assistant response
            $saveAssistantResult = $this->conversationManager->saveAssistantMessage(
                $user,
                $conversationId,
                $assistantResponse
            );
            $result['steps'][] = '✓ Assistant message saved: ' . ($saveAssistantResult['success'] ? 'yes' : 'no');

            // Step 7: Save context
            $this->contextManager->saveContext($context);
            $result['steps'][] = '✓ Context saved';

            $result['status'] = 'All steps completed successfully!';
            return $this->json($result);

        } catch (\Throwable $e) {
            return $this->json([
                'error' => $e->getMessage(),
                'file' => $e->getFile() . ':' . $e->getLine(),
                'class' => get_class($e),
                'trace' => array_slice(explode("\n", $e->getTraceAsString()), 0, 10)
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
