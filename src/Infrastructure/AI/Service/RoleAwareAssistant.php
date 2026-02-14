<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Service;

use Symfony\Bundle\SecurityBundle\Security;

/**
 * RoleAwareAssistant - Provides role-based response filtering for AI interactions.
 *
 * This service detects authenticated user roles and enforces access control
 * for role-specific AI tools and responses.
 *
 * Architecture: Infrastructure layer service (security integration)
 * DDD Role: Technical adapter for authorization
 */
class RoleAwareAssistant
{
    public const ROLE_CUSTOMER = 'ROLE_CUSTOMER';
    public const ROLE_SELLER = 'ROLE_SELLER';
    public const ROLE_ADMIN = 'ROLE_ADMIN';

    public function __construct(
        private readonly Security $security,
    ) {
    }

    /**
     * Get the current authenticated user's role
     * Returns the highest role in hierarchy: ADMIN > SELLER > CUSTOMER.
     *
     * @return string One of: ROLE_ADMIN, ROLE_SELLER, ROLE_CUSTOMER, or ROLE_ANONYMOUS
     */
    public function getCurrentUserRole(): string
    {
        $user = $this->security->getUser();

        if (!$user) {
            return 'ROLE_ANONYMOUS';
        }

        // Check roles in order of hierarchy (highest to lowest)
        if ($this->security->isGranted(self::ROLE_ADMIN)) {
            return self::ROLE_ADMIN;
        }

        if ($this->security->isGranted(self::ROLE_SELLER)) {
            return self::ROLE_SELLER;
        }

        if ($this->security->isGranted(self::ROLE_CUSTOMER)) {
            return self::ROLE_CUSTOMER;
        }

        return 'ROLE_USER'; // Default authenticated role
    }

    /**
     * Check if current user has permission for specific tool.
     *
     * @param string $toolName The name of the AI tool
     *
     * @return bool True if user has permission
     */
    public function canUseTool(string $toolName): bool
    {
        $role = $this->getCurrentUserRole();

        // Define tool permissions
        $toolPermissions = [
            // Public tools - available to all users
            'GetProductsName' => ['ROLE_ANONYMOUS', self::ROLE_CUSTOMER, self::ROLE_SELLER, self::ROLE_ADMIN],
            'GetProductsNameByMaxPrice' => ['ROLE_ANONYMOUS', self::ROLE_CUSTOMER, self::ROLE_SELLER, self::ROLE_ADMIN],
            'GetPriceByProductId' => ['ROLE_ANONYMOUS', self::ROLE_CUSTOMER, self::ROLE_SELLER, self::ROLE_ADMIN],
            'GetProductImagesByProductId' => ['ROLE_ANONYMOUS', self::ROLE_CUSTOMER, self::ROLE_SELLER, self::ROLE_ADMIN],

            // Customer tools - require authentication
            'AddToCart' => [self::ROLE_CUSTOMER, self::ROLE_SELLER, self::ROLE_ADMIN],
            'GetCartTotal' => [self::ROLE_CUSTOMER, self::ROLE_SELLER, self::ROLE_ADMIN],
            'CheckoutOrder' => [self::ROLE_CUSTOMER, self::ROLE_SELLER, self::ROLE_ADMIN],

            // Seller tools - require seller or admin role
            'GetInventory' => [self::ROLE_SELLER, self::ROLE_ADMIN],
            'UpdateStock' => [self::ROLE_SELLER, self::ROLE_ADMIN],

            // Admin tools - require admin role only
            'GetStatistics' => [self::ROLE_ADMIN],
            'ManageUsers' => [self::ROLE_ADMIN],
        ];

        if (!isset($toolPermissions[$toolName])) {
            // Unknown tool - deny by default
            return false;
        }

        return in_array($role, $toolPermissions[$toolName], true);
    }

    /**
     * Get user-friendly role name for display.
     */
    public function getRoleDisplayName(): string
    {
        return match ($this->getCurrentUserRole()) {
            self::ROLE_ADMIN => 'Administrator',
            self::ROLE_SELLER => 'Seller',
            self::ROLE_CUSTOMER => 'Customer',
            'ROLE_ANONYMOUS' => 'Guest',
            default => 'User',
        };
    }

    /**
     * Check if user is authenticated.
     */
    public function isAuthenticated(): bool
    {
        return null !== $this->security->getUser();
    }

    /**
     * Get current user ID if authenticated.
     *
     * @return string|null User ID or null if not authenticated
     */
    public function getCurrentUserId(): ?string
    {
        $user = $this->security->getUser();

        if (!$user) {
            return null;
        }

        // Assuming User entity has getId() method returning string (UUID)
        return method_exists($user, 'getId') ? (string) $user->getId() : null;
    }

    /**
     * Filter tool list based on user role
     * Returns only tools the current user can access.
     *
     * @param array<string> $allTools List of all available tool names
     *
     * @return array<string> Filtered list of accessible tools
     */
    public function filterToolsByRole(array $allTools): array
    {
        return array_filter($allTools, fn (string $tool) => $this->canUseTool($tool));
    }
}
