<?php

declare(strict_types=1);

namespace App\Command;

use App\Application\Service\FailedJobRegistry;
use App\Application\UseCase\SyncProductEmbedding;
use App\Domain\Repository\ProductRepositoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * T097: Retry Failed Embedding Sync Jobs
 * 
 * Processes dead letter queue and retries failed embedding sync operations
 * Implements exponential backoff and automatic abandonment after max retries
 * 
 * Usage:
 *   php bin/console app:retry-failed-embeddings
 *   php bin/console app:retry-failed-embeddings --limit=50
 *   php bin/console app:retry-failed-embeddings --stats-only
 */
#[AsCommand(
    name: 'app:retry-failed-embeddings',
    description: 'Retry failed embedding sync jobs from dead letter queue'
)]
class RetryFailedEmbeddingsCommand extends Command
{
    public function __construct(
        private readonly FailedJobRegistry $failedJobRegistry,
        private readonly SyncProductEmbedding $syncUseCase,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Maximum number of jobs to retry', 100)
            ->addOption('stats-only', 's', InputOption::VALUE_NONE, 'Show statistics only, do not retry')
            ->setHelp(<<<'HELP'
The <info>%command.name%</info> command retries failed embedding sync jobs.

Examples:
  <info>php %command.full_name%</info>
    Retry up to 100 failed jobs (default limit)

  <info>php %command.full_name% --limit=50</info>
    Retry up to 50 failed jobs

  <info>php %command.full_name% --stats-only</info>
    Show statistics without retrying

The command uses exponential backoff:
  - Attempt 1: Retry after 1 minute
  - Attempt 2: Retry after 5 minutes
  - Attempt 3: Retry after 30 minutes
  - Attempt 4: Retry after 2 hours
  - Attempt 5: Retry after 24 hours (final attempt)

Jobs are automatically abandoned after 5 failed attempts.
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $limit = (int) $input->getOption('limit');
        $statsOnly = (bool) $input->getOption('stats-only');

        // Display statistics
        $stats = $this->failedJobRegistry->getStatistics();
        
        $io->title('Failed Embedding Jobs Statistics');
        $io->table(
            ['Status', 'Count'],
            [
                ['Total', $stats['total']],
                ['Failed (ready for retry)', $stats['failed']],
                ['Retrying (in progress)', $stats['retrying']],
                ['Resolved (successful retry)', $stats['resolved']],
                ['Abandoned (max attempts)', $stats['abandoned']],
            ]
        );

        if ($statsOnly) {
            return Command::SUCCESS;
        }

        if ($stats['failed'] === 0) {
            $io->success('No failed jobs to retry');
            return Command::SUCCESS;
        }

        // Get jobs ready for retry
        $jobs = $this->failedJobRegistry->getJobsReadyForRetry($limit);
        $jobCount = count($jobs);

        if ($jobCount === 0) {
            $io->info('No jobs are ready for retry at this time (waiting for backoff period)');
            return Command::SUCCESS;
        }

        $io->section("Retrying {$jobCount} jobs...");
        $io->progressStart($jobCount);

        $successCount = 0;
        $failureCount = 0;
        $skippedCount = 0;

        foreach ($jobs as $job) {
            $jobId = (int) $job['id'];
            $productId = $job['product_id'];
            $operation = $job['operation'];
            $attempts = (int) $job['attempts'];

            // Mark as retrying
            $this->failedJobRegistry->markAsRetrying($jobId);

            try {
                // Fetch product from database
                $product = $this->productRepository->findById($productId);

                if ($product === null) {
                    $io->warning("Product {$productId} not found, skipping job {$jobId}");
                    $skippedCount++;
                    $this->failedJobRegistry->markAsResolved($jobId); // Resolve since product no longer exists
                    $io->progressAdvance();
                    continue;
                }

                // Retry the operation
                match ($operation) {
                    'create' => $this->syncUseCase->onCreate($product),
                    'update' => $this->syncUseCase->onUpdate($product),
                    'delete' => $this->syncUseCase->onDelete($product),
                    default => throw new \RuntimeException("Unknown operation: {$operation}"),
                };

                // Success - mark as resolved
                $this->failedJobRegistry->markAsResolved($jobId);
                $successCount++;

                $this->logger->info('Successfully retried failed job', [
                    'job_id' => $jobId,
                    'product_id' => $productId,
                    'operation' => $operation,
                    'attempts' => $attempts,
                ]);

            } catch (\Exception $e) {
                // Failure - update attempts and schedule next retry
                $this->failedJobRegistry->updateAfterRetryFailure($jobId, $attempts);
                $failureCount++;

                $this->logger->error('Failed to retry job', [
                    'job_id' => $jobId,
                    'product_id' => $productId,
                    'operation' => $operation,
                    'attempts' => $attempts + 1,
                    'error' => $e->getMessage(),
                ]);
            }

            $io->progressAdvance();
        }

        $io->progressFinish();

        // Summary
        $io->newLine();
        $io->success("Retry completed:");
        $io->listing([
            "✓ Successful: {$successCount}",
            "✗ Failed: {$failureCount}",
            "⊘ Skipped: {$skippedCount}",
        ]);

        if ($failureCount > 0) {
            $io->note('Failed jobs will be retried again after backoff period');
        }

        return Command::SUCCESS;
    }
}
