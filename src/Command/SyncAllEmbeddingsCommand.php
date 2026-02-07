<?php

declare(strict_types=1);

namespace App\Command;

use App\Application\Service\ProductEmbeddingSyncService;
use App\Domain\Repository\ProductRepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * SyncAllEmbeddingsCommand - Manual re-sync of product embeddings
 * 
 * Implements spec-010 T026: Console command for manual embedding sync
 */
#[AsCommand(
    name: 'app:sync-embeddings',
    description: 'Manually sync all product embeddings to MongoDB'
)]
class SyncAllEmbeddingsCommand extends Command
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ProductEmbeddingSyncService $syncService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'product-id',
                InputArgument::OPTIONAL,
                'Specific product ID to sync (UUID)'
            )
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Force re-sync even if embedding exists'
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Show what would be synced without actually syncing'
            )
            ->setHelp(
                <<<'HELP'
                The <info>%command.name%</info> command synchronizes product embeddings to MongoDB.
                
                Sync all products:
                  <info>php %command.full_name%</info>
                
                Sync specific product:
                  <info>php %command.full_name% 550e8400-e29b-41d4-a716-446655440000</info>
                
                Force re-sync all:
                  <info>php %command.full_name% --force</info>
                
                Dry run to preview:
                  <info>php %command.full_name% --dry-run</info>
                HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $productId = $input->getArgument('product-id');
        $force = $input->getOption('force');
        $dryRun = $input->getOption('dry-run');

        $io->title('Product Embedding Synchronization');

        if ($dryRun) {
            $io->warning('DRY RUN MODE - No changes will be made');
        }

        // Sync specific product
        if ($productId !== null) {
            return $this->syncSingleProduct($io, $productId, $force, $dryRun);
        }

        // Sync all products
        return $this->syncAllProducts($io, $force, $dryRun);
    }

    private function syncSingleProduct(
        SymfonyStyle $io,
        string $productId,
        bool $force,
        bool $dryRun
    ): int {
        $io->section('Syncing single product');

        $product = $this->productRepository->find($productId);

        if ($product === null) {
            $io->error(sprintf('Product not found: %s', $productId));
            return Command::FAILURE;
        }

        $io->text([
            sprintf('Product ID: %s', $product->getId()),
            sprintf('Name: %s', $product->getName()),
            sprintf('Category: %s', $product->getCategory()),
        ]);

        if ($dryRun) {
            $io->success('Would sync this product (dry run)');
            return Command::SUCCESS;
        }

        $io->text('Syncing...');

        try {
            $success = $this->syncService->updateEmbedding($product);

            if ($success) {
                $io->success('Product embedding synced successfully');
                return Command::SUCCESS;
            } else {
                $io->error('Failed to sync product embedding');
                return Command::FAILURE;
            }

        } catch (\Exception $e) {
            $io->error('Exception during sync: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function syncAllProducts(
        SymfonyStyle $io,
        bool $force,
        bool $dryRun
    ): int {
        $io->section('Syncing all products');

        $products = $this->productRepository->findAll();
        $totalProducts = count($products);

        if ($totalProducts === 0) {
            $io->warning('No products found in database');
            return Command::SUCCESS;
        }

        $io->text(sprintf('Found %d products to sync', $totalProducts));

        if ($dryRun) {
            $io->table(
                ['ID', 'Name', 'Category'],
                array_map(
                    fn($p) => [
                        substr($p->getId(), 0, 8) . '...',
                        substr($p->getName(), 0, 30),
                        $p->getCategory(),
                    ],
                    array_slice($products, 0, 10)
                )
            );

            if ($totalProducts > 10) {
                $io->text(sprintf('... and %d more', $totalProducts - 10));
            }

            $io->success('Would sync these products (dry run)');
            return Command::SUCCESS;
        }

        $io->progressStart($totalProducts);

        $successCount = 0;
        $failureCount = 0;
        $errors = [];

        foreach ($products as $product) {
            try {
                $success = $this->syncService->updateEmbedding($product);

                if ($success) {
                    $successCount++;
                } else {
                    $failureCount++;
                    $errors[] = sprintf(
                        'Product %s (%s) - Sync failed',
                        $product->getId(),
                        $product->getName()
                    );
                }

            } catch (\Exception $e) {
                $failureCount++;
                $errors[] = sprintf(
                    'Product %s (%s) - Exception: %s',
                    $product->getId(),
                    $product->getName(),
                    $e->getMessage()
                );
            }

            $io->progressAdvance();
        }

        $io->progressFinish();

        // Summary
        $io->section('Sync Summary');

        $io->table(
            ['Metric', 'Count'],
            [
                ['Total Products', $totalProducts],
                ['Successfully Synced', $successCount],
                ['Failed', $failureCount],
                ['Success Rate', sprintf('%.1f%%', ($successCount / $totalProducts) * 100)],
            ]
        );

        if (!empty($errors)) {
            $io->section('Errors');
            $io->listing(array_slice($errors, 0, 20));

            if (count($errors) > 20) {
                $io->text(sprintf('... and %d more errors', count($errors) - 20));
            }
        }

        if ($failureCount === 0) {
            $io->success('All products synced successfully!');
            return Command::SUCCESS;
        } elseif ($successCount > 0) {
            $io->warning('Some products failed to sync');
            return Command::FAILURE;
        } else {
            $io->error('All products failed to sync');
            return Command::FAILURE;
        }
    }
}
