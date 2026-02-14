<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Tool;

use App\Application\Message\UpdateUserEmbeddingMessage;
use App\Application\Service\SearchFacade;
use App\Application\Service\UnifiedCustomerContextManager;
use App\Domain\Entity\User;
use App\Domain\ValueObject\EventType;
use App\Domain\ValueObject\SearchQuery;
use App\Infrastructure\Queue\RabbitMQPublisher;
use Psr\Log\LoggerInterface;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * AI Tool for semantic product search in Virtual Assistant conversations.
 *
 * Implements spec-010 T063-T068: VA integration with semantic search
 *
 * Enables customers to search products using natural language:
 * - "show me gear for streaming"
 * - "I need something for home office"
 * - "laptop for gaming"
 */
#[AsTool(
    'SemanticProductSearchTool',
    'Search products using natural language and AI-powered semantic search. Use this tool whenever the customer asks about, searches for, or expresses interest in products (e.g., "I need a blender", "show me laptops", "looking for gaming gear", "professional equipment for home"). Returns relevant products with details and similarity scores. This is the PRIMARY tool for product discovery.'
)]
final class SemanticProductSearchTool
{
    public function __construct(
        private readonly SearchFacade $searchFacade,
        private readonly UnifiedCustomerContextManager $contextManager,
        private readonly LoggerInterface $aiToolsLogger,
        private readonly Security $security,
        private readonly RabbitMQPublisher $rabbitMQPublisher,
    ) {
    }

    /**
     * Search products using natural language or keywords.
     *
     * @param string      $query         Natural language query (e.g., "laptop for gaming", "streaming gear")
     * @param string|null $mode          Search mode: "semantic" (AI-powered) or "keyword" (traditional). Default: semantic
     * @param int         $limit         Maximum number of results to return (1-20). Default: 5
     * @param string|null $category      Filter by product category (optional)
     * @param float       $minSimilarity DEPRECATED - Tool uses fixed threshold of 0.3 (same as ProductController)
     * @param string|null $userId        Customer user ID for context enrichment (optional)
     *
     * @return array Search results with products and metadata
     */
    public function __invoke(
        string $query,
        ?string $mode = 'semantic',
        int $limit = 5,
        ?string $category = null,
        float $minSimilarity = 0.3,
        ?string $userId = null,
    ): array {
        try {
            // Validate and limit results to prevent overwhelming the VA
            $limit = max(1, min(20, $limit));

            // IMPORTANT: Use same similarity threshold as ProductController (0.3)
            // This ensures consistent results between AI chat and web search
            $minSimilarity = 0.3;

            $this->aiToolsLogger->info('🔍 SemanticProductSearchTool called', [
                'query' => $query,
                'mode' => $mode,
                'limit' => $limit,
                'category' => $category,
                'min_similarity' => $minSimilarity,
                'user_id' => $userId,
            ]);

            // T066: Enrich query with customer context if available
            $enrichedQuery = $this->enrichQueryWithContext($query, $userId);

            // Create search query
            $searchQuery = new SearchQuery(
                query: $enrichedQuery,
                limit: $limit,
                offset: 0,
                minSimilarity: $minSimilarity,
                category: $category
            );

            // Execute search via facade (handles mode selection and fallback)
            $result = $this->searchFacade->search($searchQuery, $mode ?? 'semantic');

            $this->aiToolsLogger->info('✅ Search completed', [
                'query' => $enrichedQuery,
                'mode' => $result->getMode(),
                'results_count' => $result->count(),
                'execution_time_ms' => $result->getExecutionTimeMs(),
            ]);

            // T071: Track semantic search usage in customer context
            if (null !== $userId) {
                $this->trackSearchInContext($userId, $query, $result->count());
            }

            // T068: Handle empty results with friendly message
            if (0 === $result->count()) {
                $this->aiToolsLogger->warning('⚠️ No products found', [
                    'query' => $enrichedQuery,
                    'category' => $category,
                    'mode' => $result->getMode(),
                ]);

                return $this->formatEmptyResults($query, $category);
            }

            // T067: Format results for VA consumption
            $formattedResults = $this->formatResults($result, $enrichedQuery);

            $this->aiToolsLogger->info('✅ Products found and formatted', [
                'count' => $result->count(),
                'product_names' => array_map(fn ($p) => $p->getName(), $result->getProducts()),
            ]);

            // spec-014: Publish search event for user embedding update
            $this->publishSearchEvent($query);

            return $formattedResults;
        } catch (\InvalidArgumentException $e) {
            // Invalid query parameters
            $this->aiToolsLogger->warning('Invalid search parameters', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'products' => [],
                'count' => 0,
                'message' => 'Search parameters are invalid. Please try with a different query.',
                'error' => $e->getMessage(),
            ];
        } catch (\Exception $e) {
            // Search service failure
            $this->aiToolsLogger->error('Semantic product search failed', [
                'query' => $query,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);

            return [
                'success' => false,
                'products' => [],
                'count' => 0,
                'message' => 'Could not perform search at this time. Please try again.',
                'error' => 'Search service temporarily unavailable',
            ];
        }
    }

    /**
     * T066: Enrich query with customer context.
     *
     * Adds contextual information from previous conversation to improve search relevance
     *
     * NOTE: Context enrichment temporarily disabled pending spec-012 full integration.
     * UnifiedCustomerContextManager requires conversationId which is not available in tool context.
     */
    private function enrichQueryWithContext(string $query, ?string $userId): string
    {
        // TODO: Re-enable after implementing conversationId propagation to tools
        return $query;
    }

    /**
     * T071: Track semantic search usage in customer context.
     *
     * NOTE: Context tracking temporarily disabled pending spec-012 full integration.
     * UnifiedCustomerContextManager requires conversationId which is not available in tool context.
     */
    private function trackSearchInContext(string $userId, string $query, int $resultCount): void
    {
        // TODO: Re-enable after implementing conversationId propagation to tools
        // Context updates should happen at the controller level, not in individual tools
    }

    /**
     * T067: Format search results for VA consumption.
     */
    private function formatResults(object $result, string $query): array
    {
        $products = [];
        $scores = $result->getScores();

        foreach ($result->getProducts() as $product) {
            $productId = $product->getId();
            $score = $scores[$productId] ?? 0.0;

            $products[] = [
                'id' => $productId,
                'name' => $product->getName(),
                'description' => $product->getDescription(),
                'price' => $product->getPrice()->getAmountAsDecimal(),
                'currency' => $product->getPrice()->getCurrency(),
                'stock' => $product->getStock(),
                'category' => $product->getCategory(),
                'similarity_score' => round($score, 2),
                'available' => $product->getStock() > 0,
            ];
        }

        $mode = $result->getMode();
        $modeLabel = 'semantic' === $mode ? 'AI-powered semantic search' : 'keyword search';

        return [
            'success' => true,
            'products' => $products,
            'count' => $result->count(),
            'search_mode' => $mode,
            'execution_time_ms' => round($result->getExecutionTimeMs(), 2),
            'message' => sprintf(
                'Found %d product(s) for "%s" using %s.',
                $result->count(),
                $query,
                $modeLabel
            ),
            'has_more' => $result->getTotalResults() > $result->count(),
        ];
    }

    /**
     * T068: Format empty results with friendly message.
     */
    private function formatEmptyResults(string $query, ?string $category): array
    {
        $message = sprintf(
            'No products found%s for "%s".',
            $category ? " in category '{$category}'" : '',
            $query
        );

        $suggestions = [
            'Try more general terms (e.g., "laptop" instead of "laptop gaming RTX 4090")',
            'Check the spelling of your search',
            'Try without specifying a category',
            'Use synonyms or alternative descriptions',
        ];

        return [
            'success' => true,
            'products' => [],
            'count' => 0,
            'message' => $message,
            'suggestions' => $suggestions,
            'alternative_action' => 'You can use ListProductsTool to see all available products.',
        ];
    }

    /**
     * Publish search event to update user embeddings.
     *
     * Publishes SEARCH event when authenticated user performs search via chat assistant
     */
    private function publishSearchEvent(string $query): void
    {
        try {
            $user = $this->security->getUser();

            if (!$user instanceof User || empty($query)) {
                return;
            }

            $message = UpdateUserEmbeddingMessage::fromDomainEvent(
                userId: $user->getId(),
                eventType: EventType::SEARCH,
                searchPhrase: $query,
                productId: null,
                occurredAt: new \DateTimeImmutable()
            );

            $this->rabbitMQPublisher->publish($message);

            $this->aiToolsLogger->info('Search event published from SemanticProductSearchTool', [
                'user_id' => $user->getId(),
                'query' => $query,
            ]);
        } catch (\Exception $e) {
            // Log but don't fail - event publishing is non-critical
            $this->aiToolsLogger->error('Failed to publish search event from tool', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
