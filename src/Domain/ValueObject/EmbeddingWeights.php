<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

/**
 * EmbeddingWeights - Configuration value object for embedding calculation parameters.
 *
 * Implements spec-014 research: Temporal decay and event weighting configuration
 * Provides centralized configuration for embedding update algorithm
 */
final readonly class EmbeddingWeights
{
    /**
     * @param float $decayLambda        Exponential decay constant (default: 0.023 for 30-day half-life)
     * @param int   $batchWindowSeconds Time window for batching events per user (default: 5 seconds)
     * @param bool  $batchEnabled       Whether to batch multiple events (default: false)
     * @param int   $maxRetries         Maximum retry attempts for failed messages (default: 5)
     * @param int   $retryDelayMs       Initial retry delay in milliseconds (default: 5000)
     */
    public function __construct(
        public float $decayLambda = 0.023,
        public int $batchWindowSeconds = 5,
        public bool $batchEnabled = false,
        public int $maxRetries = 5,
        public int $retryDelayMs = 5000,
    ) {
        if ($decayLambda <= 0) {
            throw new \InvalidArgumentException('Decay lambda must be positive');
        }
        if ($batchWindowSeconds < 1 || $batchWindowSeconds > 60) {
            throw new \InvalidArgumentException('Batch window must be between 1 and 60 seconds');
        }
        if ($maxRetries < 1 || $maxRetries > 10) {
            throw new \InvalidArgumentException('Max retries must be between 1 and 10');
        }
    }

    /**
     * Calculate decay factor for given time delta.
     *
     * Formula: decay_factor = exp(-lambda * days)
     *
     * @param int $daysSinceLastUpdate Days elapsed since last embedding update
     *
     * @return float Decay factor (0.0 to 1.0)
     */
    public function calculateDecayFactor(int $daysSinceLastUpdate): float
    {
        if ($daysSinceLastUpdate < 0) {
            throw new \InvalidArgumentException('Days since last update cannot be negative');
        }

        return exp(-$this->decayLambda * $daysSinceLastUpdate);
    }

    /**
     * Calculate half-life period in days.
     *
     * Half-life = ln(2) / lambda
     *
     * @return float Number of days for embedding weight to decay by 50%
     */
    public function getHalfLifeDays(): float
    {
        return log(2) / $this->decayLambda;
    }

    /**
     * Create from environment variables.
     */
    public static function fromEnvironment(): self
    {
        return new self(
            decayLambda: (float) ($_ENV['EMBEDDING_DECAY_LAMBDA'] ?? 0.023),
            batchWindowSeconds: (int) ($_ENV['EMBEDDING_BATCH_WINDOW'] ?? 5),
            batchEnabled: filter_var($_ENV['EMBEDDING_BATCH_ENABLED'] ?? false, FILTER_VALIDATE_BOOLEAN),
            maxRetries: (int) ($_ENV['WORKER_MAX_RETRIES'] ?? 5),
            retryDelayMs: (int) ($_ENV['WORKER_RETRY_DELAY'] ?? 5000),
        );
    }

    /**
     * Create with custom decay lambda.
     */
    public static function withDecayLambda(float $lambda): self
    {
        return new self(decayLambda: $lambda);
    }
}
