<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Domain\Entity\Product;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;

/**
 * T095: Failed Job Registry - Dead Letter Queue for embedding sync failures.
 *
 * Records failed embedding sync operations for later retry
 * Implements exponential backoff for retry scheduling
 */
class FailedJobRegistry
{
    private const MAX_ATTEMPTS = 5;
    private const RETRY_DELAYS = [
        1 => 60,      // 1 minute after first failure
        2 => 300,     // 5 minutes after second failure
        3 => 1800,    // 30 minutes after third failure
        4 => 7200,    // 2 hours after fourth failure
        5 => 86400,   // 24 hours after fifth failure (final attempt)
    ];

    public function __construct(
        private readonly Connection $connection,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Record a failed embedding sync job.
     *
     * @param string $operation create, update, or delete
     * @param int    $attempts  Current number of attempts
     */
    public function recordFailure(
        Product $product,
        string $operation,
        \Throwable $error,
        int $attempts = 0,
    ): int {
        ++$attempts;
        $retryAfter = $this->calculateRetryTime($attempts);

        try {
            $this->connection->insert('failed_embedding_jobs', [
                'product_id' => $product->getId(),
                'operation' => $operation,
                'error_message' => $error->getMessage(),
                'error_trace' => $error->getTraceAsString(),
                'payload' => json_encode([
                    'id' => $product->getId(),
                    'name' => $product->getName(),
                    'description' => substr($product->getDescription(), 0, 1000), // Truncate large descriptions
                    'category' => $product->getCategory(),
                    'price_cents' => $product->getPrice()->getAmountInCents(),
                    'currency' => $product->getPrice()->getCurrency(),
                    'stock' => $product->getStock(),
                ]),
                'attempts' => $attempts,
                'failed_at' => (new \DateTime())->format('Y-m-d H:i:s'),
                'last_retry_at' => $attempts > 1 ? (new \DateTime())->format('Y-m-d H:i:s') : null,
                'retry_after' => $retryAfter?->format('Y-m-d H:i:s'),
                'status' => $attempts >= self::MAX_ATTEMPTS ? 'abandoned' : 'failed',
            ]);

            $jobId = (int) $this->connection->lastInsertId();

            $this->logger->warning('Failed embedding job recorded', [
                'job_id' => $jobId,
                'product_id' => $product->getId(),
                'operation' => $operation,
                'attempts' => $attempts,
                'retry_after' => $retryAfter?->format('c'),
                'status' => $attempts >= self::MAX_ATTEMPTS ? 'abandoned' : 'failed',
            ]);

            return $jobId;
        } catch (\Exception $e) {
            $this->logger->error('Failed to record failed job', [
                'product_id' => $product->getId(),
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Get jobs ready for retry.
     *
     * @param int $limit Maximum number of jobs to return
     */
    public function getJobsReadyForRetry(int $limit = 100): array
    {
        try {
            return $this->connection->fetchAllAssociative('
                SELECT * FROM failed_embedding_jobs
                WHERE status = :status
                AND (retry_after IS NULL OR retry_after <= NOW())
                AND attempts < :maxAttempts
                ORDER BY failed_at ASC
                LIMIT :limit
            ', [
                'status' => 'failed',
                'maxAttempts' => self::MAX_ATTEMPTS,
                'limit' => $limit,
            ], [
                'limit' => \PDO::PARAM_INT,
                'maxAttempts' => \PDO::PARAM_INT,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to fetch jobs for retry', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Mark job as retrying.
     */
    public function markAsRetrying(int $jobId): bool
    {
        try {
            $this->connection->update('failed_embedding_jobs', [
                'status' => 'retrying',
                'last_retry_at' => (new \DateTime())->format('Y-m-d H:i:s'),
            ], [
                'id' => $jobId,
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to mark job as retrying', [
                'job_id' => $jobId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Mark job as resolved (successfully retried).
     */
    public function markAsResolved(int $jobId): bool
    {
        try {
            $this->connection->update('failed_embedding_jobs', [
                'status' => 'resolved',
                'resolved_at' => (new \DateTime())->format('Y-m-d H:i:s'),
            ], [
                'id' => $jobId,
            ]);

            $this->logger->info('Failed job resolved', ['job_id' => $jobId]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to mark job as resolved', [
                'job_id' => $jobId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Update job after retry failure (increment attempts, calculate next retry).
     */
    public function updateAfterRetryFailure(int $jobId, int $currentAttempts): bool
    {
        $attempts = $currentAttempts + 1;
        $retryAfter = $this->calculateRetryTime($attempts);

        try {
            $this->connection->update('failed_embedding_jobs', [
                'attempts' => $attempts,
                'retry_after' => $retryAfter?->format('Y-m-d H:i:s'),
                'status' => $attempts >= self::MAX_ATTEMPTS ? 'abandoned' : 'failed',
                'last_retry_at' => (new \DateTime())->format('Y-m-d H:i:s'),
            ], [
                'id' => $jobId,
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to update job after retry', [
                'job_id' => $jobId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get failure statistics.
     *
     * @return array ['total' => int, 'failed' => int, 'retrying' => int, 'resolved' => int, 'abandoned' => int]
     */
    public function getStatistics(): array
    {
        try {
            $stats = $this->connection->fetchAllAssociative('
                SELECT status, COUNT(*) as count
                FROM failed_embedding_jobs
                GROUP BY status
            ');

            $result = [
                'total' => 0,
                'failed' => 0,
                'retrying' => 0,
                'resolved' => 0,
                'abandoned' => 0,
            ];

            foreach ($stats as $row) {
                $status = $row['status'];
                $count = (int) $row['count'];
                $result[$status] = $count;
                $result['total'] += $count;
            }

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('Failed to get statistics', [
                'error' => $e->getMessage(),
            ]);

            return [
                'total' => 0,
                'failed' => 0,
                'retrying' => 0,
                'resolved' => 0,
                'abandoned' => 0,
            ];
        }
    }

    /**
     * Calculate next retry time based on attempts (exponential backoff).
     */
    private function calculateRetryTime(int $attempts): ?\DateTime
    {
        if ($attempts >= self::MAX_ATTEMPTS) {
            return null; // No more retries
        }

        $delaySeconds = self::RETRY_DELAYS[$attempts] ?? self::RETRY_DELAYS[5];
        $retryTime = new \DateTime();
        $retryTime->modify("+{$delaySeconds} seconds");

        return $retryTime;
    }
}
