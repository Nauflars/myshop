<?php

declare(strict_types=1);

namespace App\Command;

use App\Application\Message\UpdateUserEmbeddingMessage;
use App\Domain\ValueObject\EventType;
use App\Infrastructure\Queue\RabbitMQPublisher;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * GenerateTestEventsCommand - Load testing tool for user embeddings queue.
 *
 * Spec-014 Phase 8 T078: Generates N events for M users to test queue performance
 * Used for load testing and performance validation (T080-T082)
 */
#[AsCommand(
    name: 'app:generate-test-events',
    description: 'Generate test events for load testing the user embeddings queue'
)]
class GenerateTestEventsCommand extends Command
{
    private const SEARCH_PHRASES = [
        'wireless headphones',
        'laptop computer',
        'gaming mouse',
        'mechanical keyboard',
        'USB cable',
        '4K monitor',
        'webcam',
        'phone charger',
        'bluetooth speaker',
        'smart watch',
    ];

    public function __construct(
        private readonly RabbitMQPublisher $publisher,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'users',
                'u',
                InputOption::VALUE_REQUIRED,
                'Number of users to generate events for',
                100
            )
            ->addOption(
                'events-per-user',
                null,  // No shortcut (conflicts with --env)
                InputOption::VALUE_REQUIRED,
                'Number of events per user',
                50
            )
            ->addOption(
                'event-type',
                null,  // No shortcut
                InputOption::VALUE_REQUIRED,
                'Event type (search, product_view, product_click, product_purchase, mixed)',
                'mixed'
            )
            ->addOption(
                'delay-ms',
                'd',
                InputOption::VALUE_REQUIRED,
                'Delay between events in milliseconds (0 = no delay)',
                0
            )
            ->addOption(
                'start-user-id',
                's',
                InputOption::VALUE_REQUIRED,
                'Starting user ID',
                500000
            )
            ->setHelp(
                <<<'HELP'
The <info>app:generate-test-events</info> command generates synthetic events for load testing.

<info>Usage Examples:</info>

  <comment># Generate 5000 events (100 users × 50 events each)</comment>
  php bin/console app:generate-test-events --users=100 --events-per-user=50

  <comment># Generate only search events</comment>
  php bin/console app:generate-test-events --users=50 --event-type=search

  <comment># Mixed events with delay (simulate real traffic)</comment>
  php bin/console app:generate-test-events --users=20 --delay-ms=100

  <comment># Custom user ID range</comment>
  php bin/console app:generate-test-events --start-user-id=600000 --users=100

<info>Event Types:</info>
  - search: User search queries
  - product_view: Product page views
  - product_click: Product clicks in search results
  - product_purchase: Product purchases
  - mixed: Random mix of all event types (default)

<info>Performance Testing:</info>
For load test T080 (5000 events, <5 min processing), use:
  php bin/console app:generate-test-events --users=100 --events-per-user=50
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $numUsers = (int) $input->getOption('users');
        $eventsPerUser = (int) $input->getOption('events-per-user');
        $eventTypeOption = $input->getOption('event-type');
        $delayMs = (int) $input->getOption('delay-ms');
        $startUserId = (int) $input->getOption('start-user-id');

        $totalEvents = $numUsers * $eventsPerUser;

        $io->title('Generate Test Events for User Embeddings Queue');
        $io->section('Configuration');
        $io->table(
            ['Setting', 'Value'],
            [
                ['Number of Users', $numUsers],
                ['Events per User', $eventsPerUser],
                ['Total Events', $totalEvents],
                ['Event Type', $eventTypeOption],
                ['Delay (ms)', $delayMs],
                ['Start User ID', $startUserId],
            ]
        );

        if (!$io->confirm('Generate these test events?', true)) {
            $io->warning('Operation cancelled');

            return Command::SUCCESS;
        }

        $io->section('Generating Events');
        $startTime = microtime(true);

        $progressBar = new ProgressBar($output, $totalEvents);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $progressBar->start();

        $publishedCount = 0;
        $failedCount = 0;

        for ($userId = $startUserId; $userId < $startUserId + $numUsers; ++$userId) {
            for ($eventNum = 0; $eventNum < $eventsPerUser; ++$eventNum) {
                try {
                    $message = $this->generateEvent($userId, $eventTypeOption);
                    $this->publisher->publish($message);
                    ++$publishedCount;

                    if ($delayMs > 0) {
                        usleep($delayMs * 1000);
                    }
                } catch (\Exception $e) {
                    ++$failedCount;
                    $this->logger->error('Failed to publish test event', [
                        'user_id' => $userId,
                        'error' => $e->getMessage(),
                    ]);
                }

                $progressBar->advance();
            }
        }

        $progressBar->finish();
        $io->newLine(2);

        $duration = microtime(true) - $startTime;

        $io->section('Results');
        $io->success(sprintf(
            'Generated %d events in %.2f seconds (%.2f events/sec)',
            $publishedCount,
            $duration,
            $publishedCount / $duration
        ));

        $io->table(
            ['Metric', 'Value'],
            [
                ['Total Events', $totalEvents],
                ['Successfully Published', $publishedCount],
                ['Failed', $failedCount],
                ['Duration', sprintf('%.2f seconds', $duration)],
                ['Throughput', sprintf('%.2f events/sec', $publishedCount / $duration)],
                ['Average Latency', sprintf('%.2f ms/event', ($duration * 1000) / $publishedCount)],
            ]
        );

        if ($failedCount > 0) {
            $io->warning(sprintf('%d events failed to publish. Check logs for details.', $failedCount));

            return Command::FAILURE;
        }

        $io->info('Monitor queue processing:');
        $io->writeln('  - RabbitMQ UI: http://localhost:15672/#/queues/%2F/user_embedding_updates');
        $io->writeln('  - Worker logs: docker-compose logs -f worker');
        $io->writeln('  - Queue stats: php bin/console messenger:stats user_embedding_updates');

        return Command::SUCCESS;
    }

    private function generateEvent(int $userId, string $eventTypeOption): UpdateUserEmbeddingMessage
    {
        $eventType = $this->selectEventType($eventTypeOption);

        return match ($eventType) {
            EventType::SEARCH => UpdateUserEmbeddingMessage::fromDomainEvent(
                userId: $userId,
                eventType: EventType::SEARCH,
                searchPhrase: $this->randomSearchPhrase(),
                productId: null,
                occurredAt: new \DateTimeImmutable()
            ),
            EventType::PRODUCT_VIEW,
            EventType::PRODUCT_CLICK,
            EventType::PRODUCT_PURCHASE => UpdateUserEmbeddingMessage::fromDomainEvent(
                userId: $userId,
                eventType: $eventType,
                searchPhrase: null,
                productId: random_int(1, 1000),
                occurredAt: new \DateTimeImmutable()
            ),
        };
    }

    private function selectEventType(string $option): EventType
    {
        if ('mixed' === $option) {
            $types = [
                EventType::SEARCH,
                EventType::PRODUCT_VIEW,
                EventType::PRODUCT_CLICK,
                EventType::PRODUCT_PURCHASE,
            ];

            return $types[array_rand($types)];
        }

        return match ($option) {
            'search' => EventType::SEARCH,
            'product_view' => EventType::PRODUCT_VIEW,
            'product_click' => EventType::PRODUCT_CLICK,
            'product_purchase' => EventType::PRODUCT_PURCHASE,
            default => EventType::SEARCH,
        };
    }

    private function randomSearchPhrase(): string
    {
        return self::SEARCH_PHRASES[array_rand(self::SEARCH_PHRASES)];
    }
}
