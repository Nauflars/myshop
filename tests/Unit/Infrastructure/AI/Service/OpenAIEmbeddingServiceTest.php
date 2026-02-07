<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\AI\Service;

use App\Infrastructure\AI\Service\OpenAIEmbeddingService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Unit tests for OpenAIEmbeddingService
 * 
 * Tests spec-010 FR-001: Generate embeddings using OpenAI API
 * Covers: single embedding, batch embedding, error handling, retry logic
 */
class OpenAIEmbeddingServiceTest extends TestCase
{
    private LoggerInterface $logger;
    private const TEST_API_KEY = 'sk-test-key-1234567890';
    private const TEST_MODEL = 'text-embedding-3-small';

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    public function testGenerateEmbeddingSuccess(): void
    {
        // Arrange
        $mockResponse = new MockResponse(json_encode([
            'data' => [
                [
                    'embedding' => array_fill(0, 1536, 0.123456),
                    'index' => 0,
                ],
            ],
            'model' => self::TEST_MODEL,
            'usage' => [
                'prompt_tokens' => 8,
                'total_tokens' => 8,
            ],
        ]));

        $httpClient = new MockHttpClient($mockResponse);
        $service = new OpenAIEmbeddingService(
            $httpClient,
            $this->logger,
            self::TEST_API_KEY,
            self::TEST_MODEL
        );

        // Act
        $embedding = $service->generateEmbedding('test product description');

        // Assert
        $this->assertIsArray($embedding);
        $this->assertCount(1536, $embedding);
        $this->assertEquals(0.123456, $embedding[0]);
    }

    public function testGenerateBatchEmbeddingsSuccess(): void
    {
        // Arrange
        $mockResponse = new MockResponse(json_encode([
            'data' => [
                ['embedding' => array_fill(0, 1536, 0.1), 'index' => 0],
                ['embedding' => array_fill(0, 1536, 0.2), 'index' => 1],
                ['embedding' => array_fill(0, 1536, 0.3), 'index' => 2],
            ],
            'model' => self::TEST_MODEL,
            'usage' => [
                'prompt_tokens' => 24,
                'total_tokens' => 24,
            ],
        ]));

        $httpClient = new MockHttpClient($mockResponse);
        $service = new OpenAIEmbeddingService(
            $httpClient,
            $this->logger,
            self::TEST_API_KEY,
            self::TEST_MODEL
        );

        // Act
        $embeddings = $service->generateBatchEmbeddings([
            'product 1',
            'product 2',
            'product 3',
        ]);

        // Assert
        $this->assertIsArray($embeddings);
        $this->assertCount(3, $embeddings);
        $this->assertCount(1536, $embeddings[0]);
        $this->assertCount(1536, $embeddings[1]);
        $this->assertCount(1536, $embeddings[2]);
        $this->assertEquals(0.1, $embeddings[0][0]);
        $this->assertEquals(0.2, $embeddings[1][0]);
        $this->assertEquals(0.3, $embeddings[2][0]);
    }

    public function testGenerateEmbeddingInvalidResponseThrowsException(): void
    {
        // Arrange
        $mockResponse = new MockResponse(json_encode([
            'error' => 'Invalid request',
        ]));

        $httpClient = new MockHttpClient($mockResponse);
        $service = new OpenAIEmbeddingService(
            $httpClient,
            $this->logger,
            self::TEST_API_KEY,
            self::TEST_MODEL
        );

        // Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid response from OpenAI API');

        // Act
        $service->generateEmbedding('test');
    }

    public function testGenerateEmbeddingRetryLogic(): void
    {
        // Arrange - First 2 requests fail, 3rd succeeds
        $responses = [
            new MockResponse('', ['http_code' => 429]), // Rate limit
            new MockResponse('', ['http_code' => 503]), // Service unavailable
            new MockResponse(json_encode([
                'data' => [
                    ['embedding' => array_fill(0, 1536, 0.5)],
                ],
            ])),
        ];

        $httpClient = new MockHttpClient($responses);
        $service = new OpenAIEmbeddingService(
            $httpClient,
            $this->logger,
            self::TEST_API_KEY,
            self::TEST_MODEL
        );

        // Expect warning logs for retries
        $this->logger->expects($this->exactly(2))
            ->method('warning')
            ->with(
                $this->equalTo('Embedding generation failed'),
                $this->callback(function ($context) {
                    return isset($context['attempt']) && $context['attempt'] <= 3;
                })
            );

        // Act
        $embedding = $service->generateEmbedding('test');

        // Assert
        $this->assertIsArray($embedding);
        $this->assertCount(1536, $embedding);
    }

    public function testGenerateEmbeddingFailsAfterMaxRetries(): void
    {
        // Arrange - All requests fail
        $responses = [
            new MockResponse('', ['http_code' => 500]),
            new MockResponse('', ['http_code' => 500]),
            new MockResponse('', ['http_code' => 500]),
        ];

        $httpClient = new MockHttpClient($responses);
        $service = new OpenAIEmbeddingService(
            $httpClient,
            $this->logger,
            self::TEST_API_KEY,
            self::TEST_MODEL
        );

        // Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to generate embedding after 3 attempts');

        // Act
        $service->generateEmbedding('test');
    }

    public function testGetModelName(): void
    {
        // Arrange
        $httpClient = $this->createMock(HttpClientInterface::class);
        $service = new OpenAIEmbeddingService(
            $httpClient,
            $this->logger,
            self::TEST_API_KEY,
            self::TEST_MODEL
        );

        // Act & Assert
        $this->assertEquals(self::TEST_MODEL, $service->getModelName());
    }

    public function testGetDimensions(): void
    {
        // Arrange
        $httpClient = $this->createMock(HttpClientInterface::class);
        $service = new OpenAIEmbeddingService(
            $httpClient,
            $this->logger,
            self::TEST_API_KEY,
            self::TEST_MODEL
        );

        // Act & Assert
        $this->assertEquals(1536, $service->getDimensions());
    }

    public function testGetDimensionsForLargeModel(): void
    {
        // Arrange
        $httpClient = $this->createMock(HttpClientInterface::class);
        $service = new OpenAIEmbeddingService(
            $httpClient,
            $this->logger,
            self::TEST_API_KEY,
            'text-embedding-3-large'
        );

        // Act & Assert
        $this->assertEquals(3072, $service->getDimensions());
    }

    public function testGenerateBatchEmbeddingsInvalidResponseThrowsException(): void
    {
        // Arrange
        $mockResponse = new MockResponse(json_encode([
            'error' => 'Invalid batch request',
        ]));

        $httpClient = new MockHttpClient($mockResponse);
        $service = new OpenAIEmbeddingService(
            $httpClient,
            $this->logger,
            self::TEST_API_KEY,
            self::TEST_MODEL
        );

        // Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid response from OpenAI API');

        // Act
        $service->generateBatchEmbeddings(['test1', 'test2']);
    }

    public function testEmbeddingGenerationLogsDebugInfo(): void
    {
        // Arrange
        $mockResponse = new MockResponse(json_encode([
            'data' => [
                ['embedding' => array_fill(0, 1536, 0.1)],
            ],
        ]));

        $httpClient = new MockHttpClient($mockResponse);
        $service = new OpenAIEmbeddingService(
            $httpClient,
            $this->logger,
            self::TEST_API_KEY,
            self::TEST_MODEL
        );

        // Expect debug and info logs
        $this->logger->expects($this->once())
            ->method('debug')
            ->with(
                $this->equalTo('Generating embedding'),
                $this->callback(function ($context) {
                    return isset($context['model']) && isset($context['text_length']);
                })
            );

        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                $this->equalTo('Embedding generated successfully'),
                $this->callback(function ($context) {
                    return isset($context['model']) && isset($context['dimensions']);
                })
            );

        // Act
        $service->generateEmbedding('test product');
    }
}
