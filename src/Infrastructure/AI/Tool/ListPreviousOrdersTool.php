<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Tool;

use App\Application\UseCase\AI\ListPreviousOrders;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\Bundle\SecurityBundle\Security;

#[AsTool(
    'ListPreviousOrdersTool',
    'List user\'s previous orders with readable references. Use this tool when the user wants to see their order history.'
)]
final class ListPreviousOrdersTool
{
    public function __construct(
        private readonly ListPreviousOrders $listPreviousOrders,
        private readonly Security $security,
    ) {
    }

    /**
     * @param int $limit Número máximo de pedidos a mostrar (por defecto: 10)
     */
    public function __invoke(int $limit = 10): array
    {
        try {
            $user = $this->security->getUser();

            if (null === $user) {
                return [
                    'success' => false,
                    'orders' => [],
                    'message' => 'You must log in to view your orders.',
                ];
            }

            $result = $this->listPreviousOrders->execute($user, $limit);

            if (0 === $result['count']) {
                return [
                    'success' => true,
                    'orders' => [],
                    'count' => 0,
                    'message' => 'You have no previous orders. Would you like to make your first purchase?',
                ];
            }

            return [
                'success' => true,
                'orders' => $result['orders'],
                'count' => $result['count'],
                'message' => sprintf('Found %d previous order(s).', $result['count']),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'orders' => [],
                'message' => 'Could not retrieve your orders. Please try again.',
            ];
        }
    }
}
