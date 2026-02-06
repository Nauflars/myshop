<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Tool;

use App\Application\UseCase\AI\GetCartSummary;
use App\Domain\Repository\CartRepositoryInterface;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\Bundle\SecurityBundle\Security;

#[AsTool(
    'GetCartSummaryTool',
    'Obtener resumen completo del carrito con nombres de productos, cantidades, precios y total. Usa esta herramienta cuando el usuario quiera ver su carrito.'
)]
final class GetCartSummaryTool
{
    public function __construct(
        private readonly GetCartSummary $getCartSummary,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly Security $security
    ) {
    }

    public function __invoke(): array
    {
        try {
            $user = $this->security->getUser();

            if ($user === null) {
                return [
                    'success' => false,
                    'message' => 'Debes iniciar sesión para ver tu carrito.',
                    'cart' => null,
                ];
            }

            $cart = $this->cartRepository->findByUser($user);

            if ($cart === null) {
                $cart = new \App\Domain\Entity\Cart($user);
                $this->cartRepository->save($cart);
            }

            $summary = $this->getCartSummary->execute($cart);

            if ($summary['isEmpty']) {
                return [
                    'success' => true,
                    'cart' => $summary,
                    'message' => 'Tu carrito está vacío. ¿Te gustaría agregar algunos productos?',
                ];
            }

            return [
                'success' => true,
                'cart' => $summary,
                'message' => sprintf(
                    'Tu carrito contiene %d producto(s) por un total de $%.2f.',
                    $summary['itemCount'],
                    $summary['total']
                ),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'cart' => null,
                'message' => 'No se pudo obtener el resumen del carrito. Por favor intenta de nuevo.',
            ];
        }
    }
}
