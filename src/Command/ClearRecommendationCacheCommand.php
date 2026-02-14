<?php

declare(strict_types=1);

namespace App\Command;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\Cache\CacheInterface;

#[AsCommand(
    name: 'app:cache:clear-recommendations',
    description: 'Clear recommendation cache from Redis'
)]
class ClearRecommendationCacheCommand extends Command
{
    public function __construct(
        private readonly CacheInterface $cache,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'all',
            'a',
            InputOption::VALUE_NONE,
            'Clear all cache (not just recommendations)'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            if ($input->getOption('all')) {
                // Clear entire Redis cache
                if ($this->cache instanceof CacheItemPoolInterface) {
                    $this->cache->clear();
                    $io->success('All cache cleared successfully!');
                } else {
                    $io->error('Cache adapter does not support clearing all cache.');

                    return Command::FAILURE;
                }
            } else {
                // Clear only recommendation-related cache
                // Note: Symfony Cache doesn't have pattern-based deletion
                // Users need to manually delete specific keys or use Redis CLI
                $io->warning('Clearing specific recommendation cache requires knowing user IDs.');
                $io->note([
                    'To clear all recommendation cache, use one of these methods:',
                    '1. Use --all option to clear entire cache',
                    '2. Use Redis CLI: docker compose exec redis redis-cli --scan --pattern "recommendations_*"',
                    '3. Use Redis Commander web UI: http://localhost:8083',
                ]);

                return Command::SUCCESS;
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Failed to clear cache: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
