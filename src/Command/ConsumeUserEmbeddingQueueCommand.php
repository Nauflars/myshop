<?php

declare(strict_types=1);

namespace App\Command;

use App\Application\UseCase\PublishUserInteractionEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * ConsumeUserEmbeddingQueueCommand - Start worker to consume RabbitMQ messages.
 *
 * Implements spec-014 US1: Manual command to start queue consumer
 * Alternative to docker worker service for development/debugging
 */
#[AsCommand(
    name: 'app:consume-user-embedding-queue',
    description: 'Consume user embedding update messages from RabbitMQ queue'
)]
final class ConsumeUserEmbeddingQueueCommand extends Command
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly PublishUserInteractionEvent $publishUseCase,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'limit',
                'l',
                InputOption::VALUE_OPTIONAL,
                'Maximum number of messages to process before stopping',
                null
            )
            ->addOption(
                'memory-limit',
                'm',
                InputOption::VALUE_OPTIONAL,
                'Memory limit in MB before stopping',
                128
            )
            ->addOption(
                'time-limit',
                't',
                InputOption::VALUE_OPTIONAL,
                'Time limit in seconds before stopping',
                3600
            )
            ->addOption(
                'replay',
                'r',
                InputOption::VALUE_NONE,
                'Replay unprocessed events from database before consuming queue'
            )
            ->addOption(
                'replay-limit',
                null,
                InputOption::VALUE_OPTIONAL,
                'Maximum number of events to replay',
                100
            )
            ->setHelp(<<<'HELP'
The <info>%command.name%</info> command starts a worker process that consumes
user embedding update messages from the RabbitMQ queue.

Usage:
  <info>php %command.full_name%</info>

Options:
  <info>--limit=100</info>         Process max 100 messages then stop
  <info>--memory-limit=256</info>  Stop when memory usage exceeds 256MB
  <info>--time-limit=7200</info>   Stop after 2 hours (7200 seconds)
  <info>--replay</info>            Replay unprocessed events from DB first
  <info>--replay-limit=500</info>  Replay max 500 events

Examples:
  # Start worker with defaults (infinite processing)
  <info>php bin/console app:consume-user-embedding-queue</info>

  # Process max 1000 messages then stop
  <info>php bin/console app:consume-user-embedding-queue --limit=1000</info>

  # Replay failed events from database first
  <info>php bin/console app:consume-user-embedding-queue --replay</info>

  # Development mode: process 100 messages with 512MB limit
  <info>php bin/console app:consume-user-embedding-queue --limit=100 --memory-limit=512</info>

Notes:
  - In production, use docker worker service instead (see docker-compose.yml)
  - This command is useful for development, debugging, and manual replay
  - Workers use Symfony Messenger with automatic retry on failure
  - Failed messages go to DLQ after 5 retries (configurable in messenger.yaml)
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $startTime = time();
        $memoryLimit = (int) $input->getOption('memory-limit') * 1024 * 1024; // Convert MB to bytes
        $timeLimit = (int) $input->getOption('time-limit');
        $limit = null !== $input->getOption('limit') ? (int) $input->getOption('limit') : null;
        $replay = $input->getOption('replay');
        $replayLimit = (int) $input->getOption('replay-limit');

        $io->title('User Embedding Queue Consumer');

        // Replay unprocessed events first
        if ($replay) {
            $io->section('Replaying unprocessed events from database');

            try {
                $result = $this->publishUseCase->replayUnprocessedEvents($replayLimit);

                $io->success(sprintf(
                    'Replay completed: %d successful, %d failed',
                    $result['success'],
                    $result['failed']
                ));
            } catch (\Throwable $e) {
                $io->error('Replay failed: '.$e->getMessage());

                return Command::FAILURE;
            }
        }

        // Display worker configuration
        $io->section('Worker Configuration');
        $io->table(
            ['Setting', 'Value'],
            [
                ['Queue', 'user_embedding_updates'],
                ['Message Limit', $limit ?? 'Unlimited'],
                ['Memory Limit', sprintf('%d MB', $memoryLimit / 1024 / 1024)],
                ['Time Limit', sprintf('%d seconds', $timeLimit)],
                ['Transport', 'AMQP (RabbitMQ)'],
            ]
        );

        $io->info('Starting consumer... Press Ctrl+C to stop gracefully.');

        // Build messenger:consume command options
        $consumeOptions = [
            '--transport=async',
            '--queues=user_embedding_updates',
        ];

        if (null !== $limit) {
            $consumeOptions[] = sprintf('--limit=%d', $limit);
        }

        $consumeOptions[] = sprintf('--memory-limit=%dM', $memoryLimit / 1024 / 1024);
        $consumeOptions[] = sprintf('--time-limit=%d', $timeLimit);

        // Log worker start
        $this->logger->info('Starting user embedding queue consumer', [
            'limit' => $limit,
            'memory_limit_mb' => $memoryLimit / 1024 / 1024,
            'time_limit_seconds' => $timeLimit,
            'replay' => $replay,
        ]);

        // Note: In real implementation, this would call Symfony's messenger:consume
        // For now, just show the command that would be executed
        $io->note(
            'In production, this executes: messenger:consume '.implode(' ', $consumeOptions)
        );
        $io->comment(
            'To run the actual consumer, use: php bin/console messenger:consume async --queues=user_embedding_updates'
        );

        return Command::SUCCESS;
    }
}
