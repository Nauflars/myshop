<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Tool;

use App\Application\UseCase\AI\ListPreviousOrders;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\Bundle\SecurityBundle\Security;

#[AsTool(
    'ListPreviousOrdersTool',
    'Listar pedidos anteriores del usuario con referencias legibles. Usa esta herramienta cuando el usuario quiera ver su historial de pedidos.'
)]
final class ListPreviousOrdersTool
{
    public function __construct(
        private readonly ListPreviousOrders $listPreviousOrders,
        private readonly Security $security
    ) {
    }

    /**
     * @param int $limit Número máximo de pedidos a mostrar (por defecto: 10)
     */
    public function __invoke(int $limit = 10): array
    {
        try {
            $user = $this->security->getUser();

            if ($user === null) {
                return [
                    'success' => false,
                    'orders' => [],
                    'message' => 'Debes iniciar sesión para ver tus pedidos.',
                ];
            }

            $result = $this->listPreviousOrders->execute($user, $limit);

            if ($result['count'] === 0) {
                return [
                    'success' => true,
                    'orders' => [],
                    'count' => 0,
                    'message' => 'No tienes pedidos anteriores. ¿Te gustaría realizar tu primera compra?',
                ];
            }

            return [
                'success' => true,
                'orders' => $result['orders'],
                'count' => $result['count'],
                'message' => sprintf('Encontramos %d pedido(s) anterior(es).', $result['count']),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'orders' => [],
                'message' => 'No se pudieron obtener tus pedidos. Por favor intenta de nuevo.',
            ];
        }
    }
}
