<?php

namespace App\Infrastructure\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Controlador de prueba para verificar logging de Monolog en el Web Profiler.
 */
class TestMonologController extends AbstractController
{
    #[Route('/test/monolog', name: 'test_monolog', methods: ['GET'])]
    public function testMonolog(
        LoggerInterface $aiAgentLogger,
        LoggerInterface $aiToolsLogger,
        LoggerInterface $aiContextLogger,
    ): JsonResponse {
        // Simular logs del AI Agent
        $aiAgentLogger->info('🤖 Test: AI Agent started', [
            'test_id' => 'test-'.time(),
            'user_message' => 'This is a test message',
            'timestamp' => date('Y-m-d H:i:s'),
        ]);

        $aiAgentLogger->debug('🤖 Test: Processing request', [
            'context_size' => 5,
            'model' => 'gpt-4o-mini',
        ]);

        // Simular logs de Tools
        $aiToolsLogger->info('🔧 Test: SemanticProductSearchTool called', [
            'query' => 'test laptops',
            'limit' => 5,
            'mode' => 'semantic',
        ]);

        $aiToolsLogger->info('🛍 Test: AddToCartTool called', [
            'product_name' => 'Test Product',
            'quantity' => 2,
        ]);

        $aiToolsLogger->info('✅ Test: Tool execution successful', [
            'tool' => 'TestTool',
            'execution_time_ms' => 125,
            'result' => 'success',
        ]);

        // Simular logs de Contexto
        $aiContextLogger->info('📝 Test: Context loaded', [
            'conversation_id' => 'conv-test-123',
            'messages_count' => 10,
        ]);

        // Simular un warning
        $aiToolsLogger->warning('⚠️ Test: Low stock detected', [
            'product' => 'Test Product X',
            'current_stock' => 3,
        ]);

        // Simular un error
        $aiAgentLogger->error('❌ Test: Simulated error', [
            'error' => 'This is a test error',
            'code' => 500,
            'trace' => 'Stack trace would go here...',
        ]);

        return $this->json([
            'success' => true,
            'message' => '✅ Logs generated! Now open the Web Profiler to see them.',
            'instructions' => [
                '1. Look at the bottom of this page for the Symfony Debug Toolbar',
                '2. Click on the "Logs" icon (looks like a list)',
                '3. Or click the Symfony logo and then go to "Logs" tab',
                '4. Filter by channel: "ai_agent", "ai_tools", or "ai_context"',
                '5. You should see all the test logs with emojis and context data',
            ],
            'logs_generated' => [
                'ai_agent' => '3 logs (info, debug, error)',
                'ai_tools' => '4 logs (info, info, info, warning)',
                'ai_context' => '1 log (info)',
            ],
            'profiler_url' => '/_profiler (click the toolbar below to access)',
        ]);
    }

    #[Route('/test/monolog/agent', name: 'test_monolog_agent_only', methods: ['GET'])]
    public function testAgentOnly(LoggerInterface $aiAgentLogger): JsonResponse
    {
        $aiAgentLogger->info('🤖 AI AGENT CALL START', [
            'user_message' => 'show me laptops',
            'conversation_id' => 'test-conv-001',
            'user_id' => 'user-test-123',
            'messages_in_context' => 3,
            'user_roles' => ['ROLE_CUSTOMER'],
            'agent_class' => 'TestAgent',
        ]);

        $aiAgentLogger->info('🔧 Tool Calls Made', [
            'tool_calls' => [
                [
                    'name' => 'SemanticProductSearchTool',
                    'arguments' => [
                        'query' => 'laptops',
                        'limit' => 5,
                    ],
                ],
                [
                    'name' => 'AddToCartTool',
                    'arguments' => [
                        'product_name' => 'Gaming Laptop',
                        'quantity' => 1,
                    ],
                ],
            ],
        ]);

        $aiAgentLogger->info('🤖 AI AGENT CALL END', [
            'response_type' => 'string',
            'response_length' => 250,
            'execution_time_ms' => 1500,
        ]);

        return $this->json([
            'success' => true,
            'message' => 'Agent logs generated! Check the Profiler.',
            'tip' => 'These logs simulate what you see during real chatbot interactions',
        ]);
    }
}
