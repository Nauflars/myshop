<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Tool;

use App\Application\UseCase\AI\RemoveProductFromCart;
use App\Domain\Repository\CartRepositoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\Bundle\SecurityBundle\Security;

#[AsTool(
    'RemoveProductFromCartTool',
    'Remove a product from the cart using its name. Use this tool when the user wants to remove products from their cart.'
)]
final class RemoveProductFromCartTool
{
    public function __construct(
        private readonly RemoveProductFromCart $removeProductFromCart,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly Security $security,
        private readonly LoggerInterface $aiToolsLogger,
    ) {
    }

    /**
     * @param string $productName Nombre del producto a eliminar
     */
    public function __invoke(string $productName): array
    {
        $this->aiToolsLogger->info('🗑️ RemoveProductFromCartTool called', [
            'product_name' => $productName,
        ]);

        try {
            $user = $this->security->getUser();

            if (null === $user) {
                return [
                    'success' => false,
                    'message' => 'You must log in to manage your cart.',
                ];
            }

            $cart = $this->cartRepository->findByUser($user);

            if (null === $cart) {
                $cart = new \App\Domain\Entity\Cart($user);
                $this->cartRepository->save($cart);
            }

            $result = $this->removeProductFromCart->execute($cart, $productName);

            $this->aiToolsLogger->info('✅ Product removed from cart', [
                'product_name' => $productName,
                'success' => $result['success'] ?? false,
            ]);

            return $result;
        } catch (\Exception $e) {
            $this->aiToolsLogger->error('❌ RemoveProductFromCartTool failed', [
                'error' => $e->getMessage(),
                'product_name' => $productName,
            ]);

            return [
                'success' => false,
                'message' => 'No se pudo eliminar el producto del carrito. Por favor intenta de nuevo.',
            ];
        }
    }
}
