<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Tool;

use App\Application\UseCase\AI\RemoveProductFromCart;
use App\Domain\Repository\CartRepositoryInterface;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\Bundle\SecurityBundle\Security;

#[AsTool(
    'RemoveProductFromCartTool',
    'Eliminar un producto del carrito usando su nombre. Usa esta herramienta cuando el usuario quiera quitar productos de su carrito.'
)]
final class RemoveProductFromCartTool
{
    public function __construct(
        private readonly RemoveProductFromCart $removeProductFromCart,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly Security $security
    ) {
    }

    /**
     * @param string $productName Nombre del producto a eliminar
     */
    public function __invoke(string $productName): array
    {
        try {
            $user = $this->security->getUser();

            if ($user === null) {
                return [
                    'success' => false,
                    'message' => 'Debes iniciar sesión para gestionar tu carrito.',
                ];
            }

            $cart = $this->cartRepository->findByUser($user);

            if ($cart === null) {
                $cart = new \App\Domain\Entity\Cart($user);
                $this->cartRepository->save($cart);
            }

            $result = $this->removeProductFromCart->execute($cart, $productName);

            return $result;
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'No se pudo eliminar el producto del carrito. Por favor intenta de nuevo.',
            ];
        }
    }
}
