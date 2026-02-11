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
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

/**
 * AI Tool for semantic product search in Virtual Assistant conversations
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
    'Search products using natural language and AI-powered semantic search. Use this tool when the customer searches for products with natural descriptions like "something for gaming", "streaming gear", "powerful laptop". Also works with traditional keyword search. Returns relevant products with similarity scores.'
)]
final class SemanticProductSearchTool
{
    public function __construct(
        private readonly SearchFacade $searchFacade,
        private readonly UnifiedCustomerContextManager $contextManager,
        private readonly LoggerInterface $logger,
        private readonly Security $security,
        private readonly RabbitMQPublisher $rabbitMQPublisher
    ) {
    }

    /**
     * Search products using natural language or keywords
     * 
     * @param string $query Natural language query (e.g., "laptop for gaming", "streaming gear")
     * @param string|null $mode Search mode: "semantic" (AI-powered) or "keyword" (traditional). Default: semantic
     * @param int $limit Maximum number of results to return (1-20). Default: 5
     * @param string|null $category Filter by product category (optional)
     * @param float $minSimilarity Minimum similarity score (0.0-1.0). Default: 0.6
     * @param string|null $userId Customer user ID for context enrichment (optional)
     * @return array Search results with products and metadata
     */
    public function __invoke(
        string $query,
        ?string $mode = 'semantic',
        int $limit = 5,
        ?string $category = null,
        float $minSimilarity = 0.6,
        ?string $userId = null
    ): array {
        try {
            // T070: Log tool call for debugging
            $this->logger->info('SemanticProductSearchTool invoked', [
                'query' => $query,
                'mode' => $mode,
                'limit' => $limit,
                'category' => $category,
                'min_similarity' => $minSimilarity,
                'user_id' => $userId,
            ]);

            // Validate and limit results to prevent overwhelming the VA
            $limit = max(1, min(20, $limit));
            $minSimilarity = max(0.0, min(1.0, $minSimilarity));

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

            // T071: Track semantic search usage in customer context
            if ($userId !== null) {
                $this->trackSearchInContext($userId, $query, $result->count());
            }

            // T068: Handle empty results with friendly message
            if ($result->count() === 0) {
                return $this->formatEmptyResults($query, $category);
            }

            // T067: Format results for VA consumption
            $formattedResults = $this->formatResults($result, $enrichedQuery);
            
            // spec-014: Publish search event for user embedding update
            $this->publishSearchEvent($query);
            
            return $formattedResults;

        } catch (\InvalidArgumentException $e) {
            // Invalid query parameters
            $this->logger->warning('Invalid search parameters', [
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
            $this->logger->error('Semantic product search failed', [
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
     * T066: Enrich query with customer context
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
     * T071: Track semantic search usage in customer context
     * 
     * NOTE: Context tracking temporarily disabled pending spec-012 full integration.
     * UnifiedCustomerContextManager requires conversationId which is not available in tool context.
     */
    private function trackSearchInContext(string $userId, string $query, int $resultCount): void
    {
        // TODO: Re-enable after implementing conversationId propagation to tools
        // Context updates should happen at the controller level, not in individual tools
        return;
    }

    /**
     * T067: Format search results for VA consumption
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
        $modeLabel = $mode === 'semantic' ? 'búsqueda semántica con IA' : 'búsqueda por palabras clave';

        return [
            'success' => true,
            'products' => $products,
            'count' => $result->count(),
            'search_mode' => $mode,
            'execution_time_ms' => round($result->getExecutionTimeMs(), 2),
            'message' => sprintf(
                'Se encontraron %d producto(s) para "%s" usando %s.',
                $result->count(),
                $query,
                $modeLabel
            ),
            'has_more' => $result->getTotalResults() > $result->count(),
        ];
    }

    /**
     * T068: Format empty results with friendly message
     */
    private function formatEmptyResults(string $query, ?string $category): array
    {
        $message = sprintf(
            'No se encontraron productos%s para "%s".',
            $category ? " en la categoría '{$category}'" : '',
            $query
        );

        $suggestions = [
            'Intenta con términos más generales (ej: "laptop" en lugar de "laptop gaming RTX 4090")',
            'Verifica la ortografía de tu búsqueda',
            'Intenta sin especificar categoría',
            'Usa sinónimos o descripciones alternativas',
        ];

        return [
            'success' => true,
            'products' => [],
            'count' => 0,
            'message' => $message,
            'suggestions' => $suggestions,
            'alternative_action' => 'Puedes usar ListProductsTool para ver todos los productos disponibles.',
        ];
    }

    /**
     * Publish search event to update user embeddings
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
            
            $this->logger->info('Search event published from SemanticProductSearchTool', [
                'user_id' => $user->getId(),
                'query' => $query,
            ]);
        } catch (\Exception $e) {
            // Log but don't fail - event publishing is non-critical
            $this->logger->error('Failed to publish search event from tool', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
