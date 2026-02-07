<?php

declare(strict_types=1);

namespace App\Command;

use App\Application\Service\EmbeddingCacheService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Console command to clear cached query embeddings
 * 
 * Implements spec-010 T059: Cache invalidation command
 * 
 * Usage:
 *   php bin/console app:clear-embedding-cache
 *   php bin/console app:clear-embedding-cache --query="laptop"
 */
#[AsCommand(
    name: 'app:clear-embedding-cache',
    description: 'Clear cached query embeddings from Redis'
)]
class ClearEmbeddingCacheCommand extends Command
{
    public function __construct(
        private readonly EmbeddingCacheService $cacheService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'query',
                null,
                InputOption::VALUE_OPTIONAL,
                'Clear cache for specific query only (otherwise clears all)'
            )
            ->setHelp(<<<'HELP'
The <info>%command.name%</info> command clears cached query embeddings from Redis.

Clear all cached embeddings:
  <info>php %command.full_name%</info>

Clear cache for specific query:
  <info>php %command.full_name% --query="laptop for gaming"</info>

This is useful when:
- OpenAI embedding model is updated
- Cache becomes stale or corrupted
- Need to free Redis memory
- Testing cache behavior

<comment>Note:</comment> Clearing cache will cause subsequent searches to call OpenAI API,
which may increase response time and API costs temporarily.
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $query = $input->getOption('query');

        if ($query !== null) {
            // Clear specific query
            $io->info(sprintf('Clearing cache for query: "%s"', $query));
            
            $success = $this->cacheService->delete($query);
            
            if ($success) {
                $io->success(sprintf('Cache entry deleted for query: "%s"', $query));
                return Command::SUCCESS;
            } else {
                $io->error(sprintf('Failed to delete cache entry for query: "%s"', $query));
                return Command::FAILURE;
            }
            
        } else {
            // Clear all cache
            $io->warning('This will clear ALL cached query embeddings from Redis.');
            $io->note('Subsequent searches will need to call OpenAI API, increasing response time temporarily.');
            
            if (!$io->confirm('Are you sure you want to continue?', false)) {
                $io->info('Operation cancelled.');
                return Command::SUCCESS;
            }

            // Show current cache statistics before clearing
            $stats = $this->cacheService->getStats();
            $io->section('Current Cache Statistics');
            $io->table(
                ['Metric', 'Value'],
                [
                    ['Cache Hits', $stats['hits']],
                    ['Cache Misses', $stats['misses']],
                    ['Hit Rate', sprintf('%.2f%%', $stats['hit_rate'])],
                ]
            );

            $io->info('Clearing all cached embeddings...');
            
            $success = $this->cacheService->clear();
            
            if ($success) {
                $io->success('All cached embeddings cleared successfully!');
                
                $io->note([
                    'Cache statistics have been reset.',
                    'Next semantic searches will generate new embeddings via OpenAI API.',
                    'Cache will rebuild automatically as users perform searches.',
                ]);
                
                return Command::SUCCESS;
            } else {
                $io->error('Failed to clear embedding cache.');
                $io->warning('Check Redis connection and logs for details.');
                return Command::FAILURE;
            }
        }
    }
}
