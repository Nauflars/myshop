<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\ValueObject;

use App\Domain\ValueObject\EmbeddingWeights;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * EmbeddingWeightsTest - Unit tests for EmbeddingWeights configuration value object
 * 
 * Tests spec-014 T021: Embedding weights configuration with decay calculation
 */
class EmbeddingWeightsTest extends TestCase
{
    public function testConstructorWithDefaultValues(): void
    {
        $weights = new EmbeddingWeights();

        $this->assertSame(0.023, $weights->decayLambda);
        $this->assertSame(5, $weights->batchWindowSeconds);
        $this->assertFalse($weights->batchEnabled);
        $this->assertSame(5, $weights->maxRetries);
        $this->assertSame(5000, $weights->retryDelayMs);
    }

    public function testConstructorWithCustomValues(): void
    {
        $weights = new EmbeddingWeights(
            decayLambda: 0.05,
            batchWindowSeconds: 10,
            batchEnabled: true,
            maxRetries: 3,
            retryDelayMs: 1000
        );

        $this->assertSame(0.05, $weights->decayLambda);
        $this->assertSame(10, $weights->batchWindowSeconds);
        $this->assertTrue($weights->batchEnabled);
        $this->assertSame(3, $weights->maxRetries);
        $this->assertSame(1000, $weights->retryDelayMs);
    }

    public function testConstructorRejectsNegativeDecayLambda(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Decay lambda must be positive');

        new EmbeddingWeights(decayLambda: -0.01);
    }

    public function testConstructorRejectsZeroDecayLambda(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Decay lambda must be positive');

        new EmbeddingWeights(decayLambda: 0.0);
    }

    public function testConstructorRejectsTooShortBatchWindow(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Batch window must be between 1 and 60 seconds');

        new EmbeddingWeights(batchWindowSeconds: 0);
    }

    public function testConstructorRejectsTooLongBatchWindow(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Batch window must be between 1 and 60 seconds');

        new EmbeddingWeights(batchWindowSeconds: 61);
    }

    public function testConstructorRejectsTooFewRetries(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Max retries must be between 1 and 10');

        new EmbeddingWeights(maxRetries: 0);
    }

    public function testConstructorRejectsTooManyRetries(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Max retries must be between 1 and 10');

        new EmbeddingWeights(maxRetries: 11);
    }

    public function testCalculateDecayFactorNoTimeElapsed(): void
    {
        $weights = new EmbeddingWeights(decayLambda: 0.023);

        $decayFactor = $weights->calculateDecayFactor(0);

        $this->assertSame(1.0, $decayFactor); // exp(0) = 1
    }

    public function testCalculateDecayFactorOneDay(): void
    {
        $weights = new EmbeddingWeights(decayLambda: 0.023);

        $decayFactor = $weights->calculateDecayFactor(1);

        $expected = exp(-0.023 * 1);
        $this->assertEqualsWithDelta($expected, $decayFactor, 0.0001);
        $this->assertLessThan(1.0, $decayFactor);
    }

    public function testCalculateDecayFactorThirtyDays(): void
    {
        $weights = new EmbeddingWeights(decayLambda: 0.023);

        // At 30 days with lambda=0.023, should be approximately 50% decay
        $decayFactor = $weights->calculateDecayFactor(30);

        $expected = exp(-0.023 * 30);
        $this->assertEqualsWithDelta($expected, $decayFactor, 0.0001);
        $this->assertEqualsWithDelta(0.5, $decayFactor, 0.01); // ~50% for 30-day half-life
    }

    public function testCalculateDecayFactorNinetyDays(): void
    {
        $weights = new EmbeddingWeights(decayLambda: 0.023);

        $decayFactor = $weights->calculateDecayFactor(90);

        $expected = exp(-0.023 * 90);
        $this->assertEqualsWithDelta($expected, $decayFactor, 0.0001);
        $this->assertLessThan(0.2, $decayFactor); // Significant decay after 90 days
    }

    public function testCalculateDecayFactorRejectsNegativeDays(): void
    {
        $weights = new EmbeddingWeights();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Days since last update cannot be negative');

        $weights->calculateDecayFactor(-5);
    }

    public function testGetHalfLifeDays(): void
    {
        $weights = new EmbeddingWeights(decayLambda: 0.023);

        $halfLife = $weights->getHalfLifeDays();

        // Half-life = ln(2) / lambda = 0.693 / 0.023 ≈ 30 days
        $this->assertEqualsWithDelta(30.0, $halfLife, 0.5);
    }

    public function testGetHalfLifeDaysWithDifferentLambda(): void
    {
        $weights = new EmbeddingWeights(decayLambda: 0.1);

        $halfLife = $weights->getHalfLifeDays();

        // Half-life = ln(2) / 0.1 ≈ 6.93 days
        $this->assertEqualsWithDelta(6.93, $halfLife, 0.1);
    }

    public function testFromEnvironmentWithDefaults(): void
    {
        // Clear environment variables
        $_ENV = [];

        $weights = EmbeddingWeights::fromEnvironment();

        $this->assertSame(0.023, $weights->decayLambda);
        $this->assertSame(5, $weights->batchWindowSeconds);
        $this->assertFalse($weights->batchEnabled);
        $this->assertSame(5, $weights->maxRetries);
        $this->assertSame(5000, $weights->retryDelayMs);
    }

    public function testFromEnvironmentWithCustomValues(): void
    {
        $_ENV['EMBEDDING_DECAY_LAMBDA'] = '0.05';
        $_ENV['EMBEDDING_BATCH_WINDOW'] = '10';
        $_ENV['EMBEDDING_BATCH_ENABLED'] = 'true';
        $_ENV['WORKER_MAX_RETRIES'] = '7';
        $_ENV['WORKER_RETRY_DELAY'] = '3000';

        $weights = EmbeddingWeights::fromEnvironment();

        $this->assertSame(0.05, $weights->decayLambda);
        $this->assertSame(10, $weights->batchWindowSeconds);
        $this->assertTrue($weights->batchEnabled);
        $this->assertSame(7, $weights->maxRetries);
        $this->assertSame(3000, $weights->retryDelayMs);

        // Cleanup
        unset($_ENV['EMBEDDING_DECAY_LAMBDA']);
        unset($_ENV['EMBEDDING_BATCH_WINDOW']);
        unset($_ENV['EMBEDDING_BATCH_ENABLED']);
        unset($_ENV['WORKER_MAX_RETRIES']);
        unset($_ENV['WORKER_RETRY_DELAY']);
    }

    public function testWithDecayLambda(): void
    {
        $weights = EmbeddingWeights::withDecayLambda(0.1);

        $this->assertSame(0.1, $weights->decayLambda);
        // Other values should be defaults
        $this->assertSame(5, $weights->batchWindowSeconds);
        $this->assertFalse($weights->batchEnabled);
    }

    public function testDecayFactorDecreasesWithTime(): void
    {
        $weights = new EmbeddingWeights(decayLambda: 0.023);

        $decay0 = $weights->calculateDecayFactor(0);
        $decay10 = $weights->calculateDecayFactor(10);
        $decay30 = $weights->calculateDecayFactor(30);
        $decay90 = $weights->calculateDecayFactor(90);

        // Decay factor should decrease monotonically
        $this->assertGreaterThan($decay10, $decay0);
        $this->assertGreaterThan($decay30, $decay10);
        $this->assertGreaterThan($decay90, $decay30);

        // All should be between 0 and 1
        $this->assertGreaterThan(0, $decay90);
        $this->assertLessThanOrEqual(1.0, $decay0);
    }

    public function testValidBatchWindowRange(): void
    {
        // Min valid value
        $weights1 = new EmbeddingWeights(batchWindowSeconds: 1);
        $this->assertSame(1, $weights1->batchWindowSeconds);

        // Max valid value
        $weights60 = new EmbeddingWeights(batchWindowSeconds: 60);
        $this->assertSame(60, $weights60->batchWindowSeconds);
    }

    public function testValidRetriesRange(): void
    {
        // Min valid value
        $weights1 = new EmbeddingWeights(maxRetries: 1);
        $this->assertSame(1, $weights1->maxRetries);

        // Max valid value
        $weights10 = new EmbeddingWeights(maxRetries: 10);
        $this->assertSame(10, $weights10->maxRetries);
    }

    public function testDifferentDecayLambdasProduceDifferentHalfLives(): void
    {
        $weights1 = new EmbeddingWeights(decayLambda: 0.01);
        $weights2 = new EmbeddingWeights(decayLambda: 0.05);

        $halfLife1 = $weights1->getHalfLifeDays();
        $halfLife2 = $weights2->getHalfLifeDays();

        $this->assertGreaterThan($halfLife2, $halfLife1); // Smaller lambda = longer half-life
    }
}
