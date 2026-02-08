<?php

namespace App\Application\Service;

use App\Domain\Entity\User;
use App\Domain\Entity\Product;
use App\Domain\ValueObject\RecommendationResult;
use App\Domain\Repository\UserProfileRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Service for retrieving personalized product recommendations
 * 
 * Uses MongoDB vector similarity search to find products similar
 * to user's profile embedding
 */
class RecommendationService
{
    private UserProfileRepositoryInterface $profileRepository;
    private EntityManagerInterface $entityManager;
    private CacheInterface $cache;
    private LoggerInterface $logger;

    private const RECOMMENDATION_LIMIT = 20;
    private const CACHE_TTL = 1800; // 30 minutes
    private const MIN_SIMILARITY_SCORE = 0.35; // Lower threshold to show more relevant products

    public function __construct(
        UserProfileRepositoryInterface $profileRepository,
        EntityManagerInterface $entityManager,
        CacheInterface $cache,
        LoggerInterface $logger
    ) {
        $this->profileRepository = $profileRepository;
        $this->entityManager = $entityManager;
        $this->cache = $cache;
        $this->logger = $logger;
    }

    /**
     * Get personalized recommendations for user
     * 
     * Returns RecommendationResult with products ordered by similarity
     * Falls back to default recommendations if no profile exists
     */
    public function getRecommendationsForUser(User $user, int $limit = self::RECOMMENDATION_LIMIT): RecommendationResult
    {
        try {
            // Check cache first
            $cacheKey = "recommendations_{$user->getId()}_{$limit}";

            return $this->cache->get($cacheKey, function (ItemInterface $item) use ($user, $limit) {
                $item->expiresAfter(self::CACHE_TTL);

                return $this->generateRecommendations($user, $limit);
            });
        } catch (\Exception $e) {
            $this->logger->error('Failed to get recommendations', [
                'userId' => $user->getId(),
                'error' => $e->getMessage(),
            ]);

            // Return fallback recommendations on error
            return $this->getFallbackRecommendations($limit);
        }
    }

    /**
     * Generate recommendations (cache miss path)
     */
    private function generateRecommendations(User $user, int $limit): RecommendationResult
    {
        // Get user profile
        $profile = $this->profileRepository->findByUserId($user->getId());

        if (!$profile) {
            $this->logger->warning('No profile found, using fallback recommendations', [
                'userId' => $user->getId(),
            ]);
            return $this->getFallbackRecommendations($limit);
        }

        $this->logger->info('Profile found for recommendations', [
            'userId' => $user->getId(),
            'embeddingLength' => count($profile->getEmbeddingVector()),
        ]);

        // Perform vector similarity search
        $similarProducts = $this->profileRepository->findSimilarProducts(
            $profile->getEmbeddingVector(),
            $limit
        );

        $this->logger->info('Vector search completed', [
            'userId' => $user->getId(),
            'resultsCount' => count($similarProducts),
        ]);

        if (empty($similarProducts)) {
            $this->logger->warning('Vector search returned no results', [
                'userId' => $user->getId(),
            ]);
            return $this->getFallbackRecommendations($limit);
        }

        // Extract product IDs and scores
        $productIds = array_map(fn($item) => $item['productId'], $similarProducts);
        $scores = array_map(fn($item) => $item['score'], $similarProducts);

        // Filter by minimum similarity score
        $filteredData = $this->filterByMinScore($productIds, $scores);

        if (empty($filteredData['productIds'])) {
            $this->logger->warning('No products met minimum similarity threshold', [
                'userId' => $user->getId(),
                'minScore' => self::MIN_SIMILARITY_SCORE,
            ]);
            return $this->getFallbackRecommendations($limit);
        }

        // Enrich with MySQL data
        $products = $this->enrichWithMySQLData($filteredData['productIds']);

        // Match products with scores (maintain order)
        $orderedProducts = [];
        $orderedScores = [];

        foreach ($filteredData['productIds'] as $index => $productId) {
            foreach ($products as $product) {
                if ($product->getId() === $productId) {
                    $orderedProducts[] = $product;
                    $orderedScores[] = $filteredData['scores'][$index];
                    break;
                }
            }
        }

        $this->logger->info('Recommendations generated', [
            'userId' => $user->getId(),
            'count' => count($orderedProducts),
            'avgScore' => !empty($orderedScores) ? array_sum($orderedScores) / count($orderedScores) : 0,
        ]);

        return new RecommendationResult($orderedProducts, $orderedScores);
    }

    /**
     * Filter product IDs and scores by minimum similarity
     */
    private function filterByMinScore(array $productIds, array $scores): array
    {
        $filtered = ['productIds' => [], 'scores' => []];

        foreach ($productIds as $index => $productId) {
            if ($scores[$index] >= self::MIN_SIMILARITY_SCORE) {
                $filtered['productIds'][] = $productId;
                $filtered['scores'][] = $scores[$index];
            }
        }

        return $filtered;
    }

    /**
     * Enrich product IDs with full Product entities from MySQL
     * 
     * @param string[] $productIds UUID strings from MongoDB
     * @return Product[]
     */
    private function enrichWithMySQLData(array $productIds): array
    {
        if (empty($productIds)) {
            return [];
        }

        try {
            // Query products one by one since IN clause doesn't work reliably with UUID binary
            $products = [];
            $productRepo = $this->entityManager->getRepository(Product::class);
            
            foreach ($productIds as $id) {
                $product = $productRepo->find($id);
                if ($product && $product->getStock() > 0) {
                    $products[] = $product;
                }
            }

            return $products;
        } catch (\Exception $e) {
            $this->logger->error('Failed to enrich products from MySQL', [
                'productIds' => $productIds,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return [];
        }
    }

    /**
     * Get fallback recommendations (popular/featured products)
     * 
     * Used when user has no profile or vector search fails
     */
    public function getFallbackRecommendations(int $limit = self::RECOMMENDATION_LIMIT): RecommendationResult
    {
        try {
            $cacheKey = "fallback_recommendations_{$limit}";

            return $this->cache->get($cacheKey, function (ItemInterface $item) use ($limit) {
                $item->expiresAfter(3600); // 1 hour TTL for fallback

                // Get popular products (most purchased)
                $qb = $this->entityManager->createQueryBuilder();
                $qb->select('p')
                    ->from(Product::class, 'p')
                    ->where('p.stock > 0')
                    ->andWhere('p.price > 0')
                    ->orderBy('p.createdAt', 'DESC')
                    ->setMaxResults($limit);

                $products = $qb->getQuery()->getResult();

                // All products have equal "score" in fallback mode
                $scores = array_fill(0, count($products), 0.0);

                return new RecommendationResult($products, $scores);
            });
        } catch (\Exception $e) {
            $this->logger->error('Failed to get fallback recommendations', [
                'error' => $e->getMessage(),
            ]);

            // Return empty result as last resort
            return new RecommendationResult([], []);
        }
    }

    /**
     * Clear cached recommendations for user
     */
    public function clearCache(User $user): void
    {
        try {
            $cacheKey = "recommendations_{$user->getId()}_" . self::RECOMMENDATION_LIMIT;
            $this->cache->delete($cacheKey);

            $this->logger->info('Recommendation cache cleared', [
                'userId' => $user->getId(),
            ]);
        } catch (\Exception $e) {
            $this->logger->warning('Failed to clear recommendation cache', [
                'userId' => $user->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
