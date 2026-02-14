<?php

declare(strict_types=1);

namespace App\Command;

use App\Application\Service\ProductEmbeddingSyncService;
use App\Domain\Repository\ProductRepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * BatchSyncEmbeddingsCommand - Initial batch sync for large product catalogs.
 *
 * Implements spec-010 T027: Batch sync with progress tracking and error handling
 */
#[AsCommand(
    name: 'app:batch-sync-embeddings',
    description: 'Batch sync product embeddings with progress tracking'
)]
class BatchSyncEmbeddingsCommand extends Command
{
    private const DEFAULT_BATCH_SIZE = 50;

    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ProductEmbeddingSyncService $syncService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'batch-size',
                InputArgument::OPTIONAL,
                'Number of products to sync per batch',
                self::DEFAULT_BATCH_SIZE
            )
            ->setHelp(
                <<<'HELP'
                The <info>%command.name%</info> command performs batch synchronization
                of product embeddings with progress tracking and error recovery.
                
                Default batch sync:
                  <info>php %command.full_name%</info>
                
                Custom batch size:
                  <info>php %command.full_name% 100</info>
                
                This command:
                - Processes products in batches to reduce memory usage
                - Shows real-time progress
                - Continues on errors (doesn't stop entire sync)
                - Reports detailed statistics at the end
                HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $batchSize = (int) $input->getArgument('batch-size');

        $io->title('Batch Product Embedding Synchronization');

        $io->text([
            'This command will sync all products to MongoDB embeddings in batches.',
            sprintf('Batch size: %d products', $batchSize),
            '',
            'Note: This may take a while for large catalogs (~1-2s per product).',
        ]);

        if (!$io->confirm('Continue with batch sync?', true)) {
            $io->info('Batch sync cancelled');

            return Command::SUCCESS;
        }

        $products = $this->productRepository->findAll();
        $totalProducts = count($products);

        if (0 === $totalProducts) {
            $io->warning('No products found in database');

            return Command::SUCCESS;
        }

        $io->section('Starting batch sync');
        $io->text(sprintf('Total products: %d', $totalProducts));
        $io->text(sprintf('Estimated time: %d minutes', (int) ceil($totalProducts * 1.5 / 60)));

        $startTime = microtime(true);
        $io->progressStart($totalProducts);

        $successCount = 0;
        $failureCount = 0;
        $skippedCount = 0;
        $errors = [];

        $batches = array_chunk($products, $batchSize);
        $batchNumber = 0;

        foreach ($batches as $batch) {
            ++$batchNumber;
            $batchStartTime = microtime(true);

            foreach ($batch as $product) {
                try {
                    $success = $this->syncService->createEmbedding($product);

                    if ($success) {
                        ++$successCount;
                    } else {
                        ++$failureCount;
                        $errors[] = [
                            'product_id' => $product->getId(),
                            'name' => $product->getName(),
                            'error' => 'Sync returned false',
                        ];
                    }
                } catch (\Exception $e) {
                    ++$failureCount;
                    $errors[] = [
                        'product_id' => $product->getId(),
                        'name' => $product->getName(),
                        'error' => $e->getMessage(),
                    ];
                }

                $io->progressAdvance();

                // Small delay to avoid overwhelming OpenAI API
                usleep(100000); // 100ms
            }

            $batchDuration = microtime(true) - $batchStartTime;
            $avgTimePerProduct = $batchDuration / count($batch);

            // Show batch progress
            if ($output->isVerbose()) {
                $io->newLine();
                $io->text(sprintf(
                    'Batch %d/%d completed in %.2fs (avg %.2fs/product)',
                    $batchNumber,
                    count($batches),
                    $batchDuration,
                    $avgTimePerProduct
                ));
            }
        }

        $io->progressFinish();

        $totalDuration = microtime(true) - $startTime;

        // Summary
        $io->section('Batch Sync Summary');

        $io->table(
            ['Metric', 'Value'],
            [
                ['Total Products', $totalProducts],
                ['Successfully Synced', $successCount],
                ['Failed', $failureCount],
                ['Skipped', $skippedCount],
                ['Success Rate', sprintf('%.1f%%', ($successCount / $totalProducts) * 100)],
                ['Total Time', sprintf('%.2f minutes', $totalDuration / 60)],
                ['Average Time', sprintf('%.2fs per product', $totalDuration / $totalProducts)],
            ]
        );

        // Show errors if any
        if (!empty($errors)) {
            $io->section('Errors ('.count($errors).' total)');

            $errorSample = array_slice($errors, 0, 10);
            $io->table(
                ['Product ID', 'Name', 'Error'],
                array_map(
                    fn ($e) => [
                        substr($e['product_id'], 0, 8).'...',
                        substr($e['name'], 0, 30),
                        substr($e['error'], 0, 50),
                    ],
                    $errorSample
                )
            );

            if (count($errors) > 10) {
                $io->text(sprintf('... and %d more errors', count($errors) - 10));
            }

            $io->note([
                'Some products failed to sync.',
                'Review the errors above and fix any issues.',
                'Re-run this command or use app:sync-embeddings to retry failed products.',
            ]);
        }

        if (0 === $failureCount) {
            $io->success(sprintf(
                'Batch sync completed successfully! %d products synced in %.2f minutes.',
                $successCount,
                $totalDuration / 60
            ));

            return Command::SUCCESS;
        } elseif ($successCount > 0) {
            $io->warning(sprintf(
                'Batch sync completed with errors. %d succeeded, %d failed.',
                $successCount,
                $failureCount
            ));

            return Command::FAILURE;
        }
        $io->error('Batch sync failed - no products were synced successfully');

        return Command::FAILURE;
    }
}
