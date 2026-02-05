<?php

namespace App\Infrastructure\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/chat')]
#[IsGranted('ROLE_CUSTOMER')]
class ChatbotController extends AbstractController
{
    /**
     * NOTE: This is a stub implementation.
     * The full AI chatbot requires the symfony/ai package which needs to be installed.
     * 
     * To implement the full chatbot:
     * 1. Install symfony/ai package (when available)
     * 2. Configure AI provider (OpenAI/Anthropic) in config/packages/symfony_ai.yaml
     * 3. Implement ChatbotAgent class with tool registration
     * 4. Create tool classes: StatsTool, SearchProductTool, StockTool, OrderTool
     * 5. Implement SessionManager for conversation context
     */

    #[Route('', name: 'api_chatbot', methods: ['POST'])]
    public function chat(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $message = $data['message'] ?? '';

        // TODO: Implement actual AI chatbot logic here
        // For now, returning a simple response
        
        $response = $this->generateSimpleResponse($message);

        return $this->json([
            'response' => $response,
            'role' => 'bot',
        ]);
    }

    private function generateSimpleResponse(string $message): string
    {
        $message = strtolower($message);

        // Simple keyword-based responses
        if (str_contains($message, 'product') || str_contains($message, 'search')) {
            return 'You can browse our products by visiting the Products page. We have Electronics, Clothing, Books, and Home categories available.';
        }

        if (str_contains($message, 'cart')) {
            return 'To manage your cart, visit the Cart page. You can add products by clicking "Add to Cart" on any product page.';
        }

        if (str_contains($message, 'order')) {
            return 'You can view your order history on the Orders page. To place a new order, add items to your cart and proceed to checkout.';
        }

        if (str_contains($message, 'stock')) {
            return 'Product stock information is displayed on each product page. If a product shows "In Stock", you can add it to your cart.';
        }

        if (str_contains($message, 'help')) {
            return 'I can help you with: searching for products, managing your cart, tracking orders, and checking stock availability. What would you like to know?';
        }

        return 'I\'m here to help! Ask me about products, your cart, orders, or stock availability. '
            . 'For full AI capabilities, the symfony/ai integration needs to be configured.';
    }
}
