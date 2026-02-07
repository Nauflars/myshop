<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Domain\ValueObject\SearchQuery;
use App\Domain\ValueObject\SearchResult;
use Psr\Log\LoggerInterface;

/**
 * SearchFacade - Route between semantic and keyword search modes
 * 
 * Implements spec-010 T042-T047: Mode switching and error handling with fallbacks
 */
class SearchFacade
{
    private const MODE_SEMANTIC = 'semantic';
    private const MODE_KEYWORD = 'keyword';
    private const VALID_MODES = [self::MODE_SEMANTIC, self::MODE_KEYWORD];

    public function __construct(
        private readonly SemanticSearchService $semanticSearchService,
        private readonly KeywordSearchService $keywordSearchService,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Execute search with specified mode and automatic fallback
     */
    public function search(SearchQuery $searchQuery, string $mode = self::MODE_SEMANTIC): SearchResult
    {
        $validatedMode = $this->validateMode($mode);

        $this->logger->info('Search request received', [
            'query' => $searchQuery->getQuery(),
            'mode' => $validatedMode,
            'limit' => $searchQuery->getLimit(),
        ]);

        if ($validatedMode === self::MODE_SEMANTIC) {
            return $this->executeSemanticSearch($searchQuery);
        }

        return $this->executeKeywordSearch($searchQuery);
    }

    /**
     * Execute semantic search with fallback to keyword on failure
     */
    private function executeSemanticSearch(SearchQuery $searchQuery): SearchResult
    {
        try {
            return $this->semanticSearchService->search($searchQuery);

        } catch (\Exception $e) {
            $this->logger->warning('Semantic search failed, falling back to keyword search', [
                'query' => $searchQuery->getQuery(),
                'error' => $e->getMessage(),
            ]);

            // Fallback to keyword search
            return $this->executeKeywordSearch($searchQuery);
        }
    }

    /**
     * Execute keyword search
     */
    private function executeKeywordSearch(SearchQuery $searchQuery): SearchResult
    {
        try {
            return $this->keywordSearchService->search($searchQuery);

        } catch (\Exception $e) {
            $this->logger->error('Keyword search failed', [
                'query' => $searchQuery->getQuery(),
                'error' => $e->getMessage(),
            ]);

            // Return empty result
            return new SearchResult(
                products: [],
                scores: [],
                mode: 'keyword',
                totalResults: 0,
                executionTimeMs: 0.0
            );
        }
    }

    /**
     * Validate and normalize search mode
     */
    private function validateMode(string $mode): string
    {
        $normalized = strtolower(trim($mode));

        if (!in_array($normalized, self::VALID_MODES, true)) {
            $this->logger->warning('Invalid search mode, defaulting to semantic', [
                'provided_mode' => $mode,
                'valid_modes' => self::VALID_MODES,
            ]);

            return self::MODE_SEMANTIC;
        }

        return $normalized;
    }

    /**
     * Get available search modes
     * 
     * @return array<string>
     */
    public function getAvailableModes(): array
    {
        return self::VALID_MODES;
    }

    /**
     * Check if semantic search is available
     */
    public function isSemanticSearchAvailable(): bool
    {
        // Could add health check for OpenAI and MongoDB here
        return true;
    }
}
