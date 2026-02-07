<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Domain\Entity\AdminAssistantAction;
use App\Domain\Entity\AdminAssistantConversation;
use App\Domain\Entity\User;
use App\Infrastructure\Repository\AdminAssistantActionRepository;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * AdminAssistantLogger - Logs all admin assistant actions for audit trail
 * 
 * Part of spec-007: Admin Virtual Assistant
 * Provides compliance and security auditing capabilities
 */
class AdminAssistantLogger
{
    public function __construct(
        private readonly AdminAssistantActionRepository $repository,
        private readonly RequestStack $requestStack
    ) {
    }

    /**
     * Log a product creation action
     */
    public function logProductCreation(
        User $adminUser,
        string $productId,
        array $productData,
        ?AdminAssistantConversation $conversation = null
    ): AdminAssistantAction {
        $action = new AdminAssistantAction(
            $adminUser,
            AdminAssistantAction::ACTION_CREATE_PRODUCT,
            $conversation
        );

        $action->addAffectedEntity('product_id', $productId);
        $action->setActionParameters([
            'name' => $productData['name'] ?? null,
            'price' => $productData['price'] ?? null,
            'category' => $productData['category'] ?? null,
            'stock' => $productData['stock'] ?? null,
        ]);
        $action->setActionResult([
            'message' => 'Product created successfully',
            'product_id' => $productId,
        ]);

        $this->enrichWithRequestData($action);
        $this->repository->save($action);

        return $action;
    }

    /**
     * Log a product update action
     */
    public function logProductUpdate(
        User $adminUser,
        string $productId,
        array $changes,
        ?AdminAssistantConversation $conversation = null
    ): AdminAssistantAction {
        $action = new AdminAssistantAction(
            $adminUser,
            AdminAssistantAction::ACTION_UPDATE_PRODUCT,
            $conversation
        );

        $action->addAffectedEntity('product_id', $productId);
        $action->setActionParameters(['changes' => $changes]);
        $action->setActionResult([
            'message' => 'Product updated successfully',
            'fields_updated' => array_keys($changes),
        ]);

        $this->enrichWithRequestData($action);
        $this->repository->save($action);

        return $action;
    }

    /**
     * Log a product deletion action
     */
    public function logProductDeletion(
        User $adminUser,
        string $productId,
        string $productName,
        bool $success,
        ?string $errorMessage = null,
        ?AdminAssistantConversation $conversation = null
    ): AdminAssistantAction {
        $action = new AdminAssistantAction(
            $adminUser,
            AdminAssistantAction::ACTION_DELETE_PRODUCT,
            $conversation
        );

        $action->addAffectedEntity('product_id', $productId);
        $action->setActionParameters(['product_name' => $productName]);

        if ($success) {
            $action->markAsSuccess();
            $action->setActionResult(['message' => 'Product deleted successfully']);
        } else {
            $action->markAsFailed($errorMessage ?? 'Unknown error');
        }

        $this->enrichWithRequestData($action);
        $this->repository->save($action);

        return $action;
    }

    /**
     * Log a sales overview query
     */
    public function logSalesQuery(
        User $adminUser,
        array $queryResult,
        ?AdminAssistantConversation $conversation = null
    ): AdminAssistantAction {
        $action = new AdminAssistantAction(
            $adminUser,
            AdminAssistantAction::ACTION_QUERY_SALES,
            $conversation
        );

        $action->setActionResult([
            'total_revenue' => $queryResult['total_revenue'] ?? 0,
            'order_count' => $queryResult['order_count'] ?? 0,
            'average_order_value' => $queryResult['average_order_value'] ?? 0,
        ]);

        $this->enrichWithRequestData($action);
        $this->repository->save($action);

        return $action;
    }

    /**
     * Log a product statistics query
     */
    public function logProductStatsQuery(
        User $adminUser,
        string $productId,
        array $queryResult,
        ?AdminAssistantConversation $conversation = null
    ): AdminAssistantAction {
        $action = new AdminAssistantAction(
            $adminUser,
            AdminAssistantAction::ACTION_QUERY_PRODUCT_STATS,
            $conversation
        );

        $action->addAffectedEntity('product_id', $productId);
        $action->setActionResult($queryResult);

        $this->enrichWithRequestData($action);
        $this->repository->save($action);

        return $action;
    }

    /**
     * Log a top products query
     */
    public function logTopProductsQuery(
        User $adminUser,
        int $resultCount,
        ?AdminAssistantConversation $conversation = null
    ): AdminAssistantAction {
        $action = new AdminAssistantAction(
            $adminUser,
            AdminAssistantAction::ACTION_QUERY_TOP_PRODUCTS,
            $conversation
        );

        $action->setActionResult(['product_count' => $resultCount]);

        $this->enrichWithRequestData($action);
        $this->repository->save($action);

        return $action;
    }

    /**
     * Log a user purchase statistics query
     */
    public function logUserStatsQuery(
        User $adminUser,
        int $resultCount,
        ?AdminAssistantConversation $conversation = null
    ): AdminAssistantAction {
        $action = new AdminAssistantAction(
            $adminUser,
            AdminAssistantAction::ACTION_QUERY_USER_STATS,
            $conversation
        );

        $action->setActionResult(['user_count' => $resultCount]);

        $this->enrichWithRequestData($action);
        $this->repository->save($action);

        return $action;
    }

    /**
     * Log a stock update action (spec-008 US2)
     */
    public function logStockUpdate(
        string $productId,
        string $productName,
        int $oldStock,
        int $newStock,
        string $mode,
        int $quantity,
        ?string $reason = null,
        ?AdminAssistantConversation $conversation = null
    ): AdminAssistantAction {
        // Get current admin user from conversation if available
        $adminUser = $conversation?->getAdminUser() ?? throw new \LogicException('Admin user required for stock update audit');

        $action = new AdminAssistantAction(
            $adminUser,
            AdminAssistantAction::ACTION_UPDATE_STOCK,
            $conversation
        );

        $action->addAffectedEntity('product_id', $productId);
        $action->setActionParameters([
            'product_name' => $productName,
            'mode' => $mode,
            'quantity' => $quantity,
            'old_stock' => $oldStock,
            'new_stock' => $newStock,
            'reason' => $reason,
        ]);
        $action->setActionResult([
            'message' => 'Stock updated successfully',
            'delta' => $newStock - $oldStock,
        ]);

        $this->enrichWithRequestData($action);
        $this->repository->save($action);

        return $action;
    }

    /**
     * Log a failed action
     */
    public function logFailedAction(
        User $adminUser,
        string $actionType,
        string $errorMessage,
        ?array $context = null,
        ?AdminAssistantConversation $conversation = null
    ): AdminAssistantAction {
        $action = new AdminAssistantAction(
            $adminUser,
            $actionType,
            $conversation
        );

        $action->markAsFailed($errorMessage);
        
        if ($context !== null) {
            $action->setActionParameters($context);
        }

        $this->enrichWithRequestData($action);
        $this->repository->save($action);

        return $action;
    }

    /**
     * Enrich action with HTTP request data
     */
    private function enrichWithRequestData(AdminAssistantAction $action): void
    {
        $request = $this->requestStack->getCurrentRequest();
        
        if ($request === null) {
            return;
        }

        // Get client IP address
        $ip = $request->getClientIp();
        if ($ip !== null) {
            $action->setIpAddress($ip);
        }

        // Get user agent
        $userAgent = $request->headers->get('User-Agent');
        if ($userAgent !== null) {
            $action->setUserAgent($userAgent);
        }
    }
}
