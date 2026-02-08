<?php

namespace App\Application\Service;

use App\Domain\Entity\User;
use App\Domain\Entity\Order;
use App\Domain\ValueObject\ProfileSnapshot;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Aggregates user behavior data for profile generation
 * 
 * Collects purchase history, search queries, and browsing behavior
 * with weighted importance: Purchases (70%), Searches (20%), Views (10%)
 */
class ProfileAggregationService
{
    private EntityManagerInterface $entityManager;
    private CacheInterface $cache;
    private LoggerInterface $logger;

    // Configuration
    private const RECENT_PURCHASES_LIMIT = 20;
    private const RECENT_SEARCHES_LIMIT = 50;
    private const RECENCY_DECAY_DAYS = 90;

    public function __construct(
        EntityManagerInterface $entityManager,
        CacheInterface $cache,
        LoggerInterface $logger
    ) {
        $this->entityManager = $entityManager;
        $this->cache = $cache;
        $this->logger = $logger;
    }

    /**
     * Aggregate all user data into a ProfileSnapshot
     */
    public function aggregateUserData(User $user): ProfileSnapshot
    {
        $purchases = $this->aggregatePurchases($user);
        $searches = $this->aggregateSearches($user);
        $categories = $this->extractDominantCategories($purchases);

        return new ProfileSnapshot($purchases, $searches, $categories);
    }

    /**
     * Aggregate purchase history
     * 
     * Returns array of product names from recent completed orders
     * with recency decay applied for purchases older than 90 days
     * 
     * @return string[]
     */
    public function aggregatePurchases(User $user): array
    {
        try {
            // Get order repository
            $orderRepository = $this->entityManager->getRepository(Order::class);
            
            // Query orders with completed status for this user
            $allOrders = $orderRepository->findBy(['user' => $user]);
            
            // Filter by status and sort
            $completedOrders = array_filter($allOrders, function($order) {
                $status = $order->getStatus();
                return $status === Order::STATUS_DELIVERED || $status === Order::STATUS_SHIPPED;
            });
            
            // Sort by updatedAt descending
            usort($completedOrders, function($a, $b) {
                return $b->getUpdatedAt() <=> $a->getUpdatedAt();
            });
            
            // Limit to recent purchases
            $orders = array_slice($completedOrders, 0, self::RECENT_PURCHASES_LIMIT);

            $purchases = [];
            $now = new \DateTimeImmutable();

            foreach ($orders as $order) {
                $updatedAt = $order->getUpdatedAt();
                if (!$updatedAt) {
                    continue;
                }

                // Calculate age in days
                $ageInDays = $now->diff($updatedAt)->days;
                
                // Apply recency decay
                $weight = $this->calculateRecencyWeight($ageInDays);

                // Load items lazily
                foreach ($order->getItems() as $item) {
                    $product = $item->getProduct();
                    $productName = $product->getName();

                    // Repeat product name based on weight (1x to 3x)
                    $repetitions = max(1, (int) ceil($weight * 3));
                    for ($i = 0; $i < $repetitions; $i++) {
                        $purchases[] = $productName;
                    }
                }
            }

            $this->logger->info('Aggregated purchases', [
                'userId' => $user->getId(),
                'purchaseCount' => count($purchases),
            ]);

            return $purchases;
        } catch (\Exception $e) {
            $this->logger->error('Failed to aggregate purchases', [
                'userId' => $user->getId(),
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Aggregate search queries from conversation history
     * 
     * Retrieves recent search queries from Redis conversation context
     * 
     * @return string[]
     */
    public function aggregateSearches(User $user): array
    {
        try {
            // Use cache to get search queries from conversation history
            $cacheKey = "user_searches_{$user->getId()}";

            return $this->cache->get($cacheKey, function (ItemInterface $item) use ($user) {
                $item->expiresAfter(3600); // 1 hour TTL

                // Query conversation history from database
                // For now, return empty array - this would be implemented
                // with actual conversation tracking from spec-012
                
                // TODO: Implement search query extraction from UnifiedConversation
                return [];
            });
        } catch (\Exception $e) {
            $this->logger->error('Failed to aggregate searches', [
                'userId' => $user->getId(),
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Extract dominant categories from purchases
     * 
     * @param string[] $purchases
     * @return string[]
     */
    private function extractDominantCategories(array $purchases): array
    {
        if (empty($purchases)) {
            return [];
        }

        try {
            // Query categories for purchased products
            $qb = $this->entityManager->createQueryBuilder();
            $qb->select('c.name, COUNT(p.id) as purchaseCount')
                ->from('App\\Domain\\Entity\\Product', 'p')
                ->join('p.category', 'c')
                ->where($qb->expr()->in('p.name', ':purchases'))
                ->setParameter('purchases', array_unique($purchases))
                ->groupBy('c.id')
                ->orderBy('purchaseCount', 'DESC')
                ->setMaxResults(5);

            $results = $qb->getQuery()->getResult();

            return array_map(fn($row) => $row['name'], $results);
        } catch (\Exception $e) {
            $this->logger->error('Failed to extract categories', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Calculate recency weight for purchases
     * 
     * Returns 1.0 for recent purchases, decays to 0.5 for old purchases
     */
    private function calculateRecencyWeight(int $ageInDays): float
    {
        if ($ageInDays <= self::RECENCY_DECAY_DAYS) {
            return 1.0;
        }

        // Linear decay from 1.0 to 0.5 over 90 days past threshold
        $daysPastThreshold = $ageInDays - self::RECENCY_DECAY_DAYS;
        $decayFactor = max(0.5, 1.0 - ($daysPastThreshold / 90.0) * 0.5);

        return $decayFactor;
    }

    /**
     * Build text representation for embedding generation
     * 
     * Applies weighted aggregation:
     * - Purchases: 70% (repeat 7 times)
     * - Searches: 20% (repeat 2 times)
     * - Categories: 10% (single occurrence)
     */
    public function buildTextRepresentation(ProfileSnapshot $snapshot): string
    {
        return $snapshot->toWeightedText();
    }

    /**
     * Get metadata for user profile
     */
    public function getUserMetadata(User $user): array
    {
        try {
            $qb = $this->entityManager->createQueryBuilder();
            $qb->select('COUNT(o.id) as totalOrders')
                ->from(Order::class, 'o')
                ->where('o.user = :user')
                ->andWhere($qb->expr()->in('o.status', ['DELIVERED', 'SHIPPED']))
                ->setParameter('user', $user);

            $result = $qb->getQuery()->getSingleScalarResult();

            $accountAge = $user->getCreatedAt()->diff(new \DateTimeImmutable())->days;

            return [
                'totalPurchases' => (int) $result,
                'totalSearches' => 0, // TODO: Implement from conversation tracking
                'accountAgeDays' => $accountAge,
                'lastActivityDate' => new \DateTimeImmutable(),
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to get user metadata', [
                'userId' => $user->getId(),
                'error' => $e->getMessage(),
            ]);
            return [
                'totalPurchases' => 0,
                'totalSearches' => 0,
                'accountAgeDays' => 0,
                'lastActivityDate' => new \DateTimeImmutable(),
            ];
        }
    }
}
