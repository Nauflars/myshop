<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Tool;

use App\Application\Service\SearchFacade;
use App\Application\Service\CustomerContextManager;
use App\Domain\ValueObject\SearchQuery;
use Psr\Log\LoggerInterface;
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
    'Buscar productos usando lenguaje natural y búsqueda semántica con IA. Usa esta herramienta cuando el cliente busque productos con descripciones naturales como "algo para gaming", "equipo para streaming", "laptop potente". También funciona con búsqueda tradicional por palabras clave. Retorna productos relevantes con scores de similitud.'
)]
final class SemanticProductSearchTool
{
    public function __construct(
        private readonly SearchFacade $searchFacade,
        private readonly CustomerContextManager $contextManager,
        private readonly LoggerInterface $logger
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
            return $this->formatResults($result, $enrichedQuery);

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
                'message' => 'Los parámetros de búsqueda no son válidos. Por favor, intenta con una consulta diferente.',
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
                'message' => 'No se pudo realizar la búsqueda en este momento. Por favor intenta de nuevo.',
                'error' => 'Search service temporarily unavailable',
            ];
        }
    }

    /**
     * T066: Enrich query with customer context
     * 
     * Adds contextual information from previous conversation to improve search relevance
     */
    private function enrichQueryWithContext(string $query, ?string $userId): string
    {
        if ($userId === null) {
            return $query;
        }

        try {
            $context = $this->contextManager->loadContext($userId);
            
            if ($context === null) {
                return $query;
            }

            // Extract relevant context for search enrichment
            $preferences = $context->getCustomerPreferences();
            
            // Add category preference if available and not explicitly specified in query
            if (isset($preferences['preferred_category']) && !empty($preferences['preferred_category'])) {
                $preferredCategory = $preferences['preferred_category'];
                
                // Only add if query doesn't already mention the category
                if (stripos($query, $preferredCategory) === false) {
                    $this->logger->debug('Enriching query with category preference', [
                        'original_query' => $query,
                        'preferred_category' => $preferredCategory,
                    ]);
                    // Note: Not modifying query to avoid over-constraint
                    // Context enrichment is passive for now
                }
            }

            return $query;

        } catch (\Exception $e) {
            $this->logger->warning('Failed to enrich query with context', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return $query;
        }
    }

    /**
     * T071: Track semantic search usage in customer context
     */
    private function trackSearchInContext(string $userId, string $query, int $resultCount): void
    {
        try {
            $context = $this->contextManager->getOrCreateContext($userId);
            
            // Update conversation state to indicate search activity
            $context->setFlow('product_search');
            
            // Track search in customer preferences
            $preferences = $context->getCustomerPreferences();
            $preferences['last_search_query'] = $query;
            $preferences['last_search_results_count'] = $resultCount;
            $preferences['search_count'] = ($preferences['search_count'] ?? 0) + 1;
            
            $context->setCustomerPreferences($preferences);
            
            // Save updated context
            $this->contextManager->saveContext($context);

            $this->logger->debug('Search activity tracked in customer context', [
                'user_id' => $userId,
                'query' => $query,
                'result_count' => $resultCount,
            ]);

        } catch (\Exception $e) {
            // Don't fail the search if context tracking fails
            $this->logger->warning('Failed to track search in context', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }
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
}
