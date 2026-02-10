<?php

declare(strict_types=1);

namespace App\Command;

use App\Application\UseCase\PublishUserInteractionEvent;
use App\Repository\UserInteractionRepository;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * ReplayUserEventsCommand - Replay unprocessed or failed events from MySQL
 * 
 * Implements spec-014 US4: Manual replay capability for failed events
 * Useful for recovering from RabbitMQ downtime or worker failures
 */
#[AsCommand(
    name: 'app:replay-user-events',
    description: 'Replay unprocessed or failed user interaction events from MySQL to RabbitMQ'
)]
final class ReplayUserEventsCommand extends Command
{
    public function __construct(
        private readonly PublishUserInteractionEvent $publishUseCase,
        private readonly UserInteractionRepository $userInteractionRepository,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'user-id',
                'u',
                InputOption::VALUE_OPTIONAL,
                'Replay events for specific user ID'
            )
            ->addOption(
                'since',
                's',
                InputOption::VALUE_OPTIONAL,
                'Replay events since date (e.g., "2026-02-01", "-7 days")',
                '-7 days'
            )
            ->addOption(
                'limit',
                'l',
                InputOption::VALUE_OPTIONAL,
                'Maximum number of events to replay',
                1000
            )
            ->addOption(
                'unprocessed-only',
                null,
                InputOption::VALUE_NONE,
                'Only replay events that were never processed (processed_to_queue = false)'
            )
            ->addOption(
                'dry-run',
                'd',
                InputOption::VALUE_NONE,
                'Show what would be replayed without actually publishing'
            )
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Skip confirmation prompt'
            )
            ->setHelp(<<<'HELP'
The <info>%command.name%</info> command replays user interaction events from MySQL 
to RabbitMQ. This is useful for recovering from:
  - RabbitMQ downtime
  - Worker failures
  - Network issues
  - Failed message processing

Usage Examples:

  # Replay last 7 days of unprocessed events (with confirmation)
  <info>php %command.full_name% --unprocessed-only</info>

  # Replay all events from specific user
  <info>php %command.full_name% --user-id=12345</info>

  # Replay events since specific date
  <info>php %command.full_name% --since="2026-02-01"</info>

  # Replay last 24 hours (max 500 events)
  <info>php %command.full_name% --since="-1 day" --limit=500</info>

  # Dry run to see what would be replayed
  <info>php %command.full_name% --dry-run</info>

  # Force replay without confirmation (use in scripts)
  <info>php %command.full_name% --force --unprocessed-only</info>

Options:
  <info>--user-id=ID</info>           Filter by specific user
  <info>--since=DATE</info>           Only events after this date
  <info>--limit=N</info>              Max events to process (default: 1000)
  <info>--unprocessed-only</info>     Only events with processed_to_queue=false
  <info>--dry-run</info>              Preview without publishing
  <info>--force</info>                Skip confirmation

HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $userId = $input->getOption('user-id') ? (int) $input->getOption('user-id') : null;
        $since = $input->getOption('since');
        $limit = (int) $input->getOption('limit');
        $unprocessedOnly = $input->getOption('unprocessed-only');
        $dryRun = $input->getOption('dry-run');
        $force = $input->getOption('force');

        $io->title('Replay User Events from MySQL to RabbitMQ');

        try {
            // Parse since date
            $sinceDate = new DateTimeImmutable($since);

            // Build query criteria
            $io->section('Query Criteria');
            $io->table(
                ['Criterion', 'Value'],
                [
                    ['User ID', $userId ?? 'All users'],
                    ['Since Date', $sinceDate->format('Y-m-d H:i:s')],
                    ['Max Events', $limit],
                    ['Filter', $unprocessedOnly ? 'Unprocessed only' : 'All events'],
                    ['Mode', $dryRun ? 'DRY RUN (no publishing)' : 'Live publishing'],
                ]
            );

            // Fetch events to replay
            if ($unprocessedOnly) {
                $events = $this->userInteractionRepository->findUnprocessedEvents($limit);
            } else {
                $events = $this->userInteractionRepository->findRecent($sinceDate, $limit);
            }

            // Filter by user ID if specified
            if ($userId !== null) {
                $events = array_filter($events, fn($event) => $event->getUserId() === $userId);
            }

            if (empty($events)) {
                $io->success('No events found matching criteria');
                return Command::SUCCESS;
            }

            $io->section('Events Found');
            $io->text(sprintf('Found <info>%d</info> events to replay', count($events)));

            // Show sample events
            $sampleSize = min(10, count($events));
            $sampleEvents = array_slice($events, 0, $sampleSize);
            
            $tableData = [];
            foreach ($sampleEvents as $event) {
                $tableData[] = [
                    $event->getId(),
                    $event->getUserId(),
                    $event->getEventType()->value,
                    $event->getOccurredAt()->format('Y-m-d H:i:s'),
                    $event->isProcessedToQueue() ? 'Yes' : 'No',
                ];
            }

            $io->table(
                ['ID', 'User ID', 'Event Type', 'Occurred At', 'Processed'],
                $tableData
            );

            if (count($events) > $sampleSize) {
                $io->text(sprintf('... and %d more events', count($events) - $sampleSize));
            }

            // Confirmation prompt (unless dry-run or force)
            if (!$dryRun && !$force) {
                if (!$io->confirm(
                    sprintf('Replay %d events to RabbitMQ?', count($events)),
                    false
                )) {
                    $io->warning('Replay cancelled');
                    return Command::SUCCESS;
                }
            }

            // Replay events
            if ($dryRun) {
                $io->info('DRY RUN: No events were actually published');
                return Command::SUCCESS;
            }

            $io->section('Replaying Events');
            $io->progressStart(count($events));

            $success = 0;
            $failed = 0;
            $errors = [];

            foreach ($events as $event) {
                try {
                    $published = $this->publishUseCase->execute($event);
                    
                    if ($published) {
                        $success++;
                    } else {
                        $failed++;
                        $errors[] = sprintf(
                            'Event #%d (user %d) failed to publish',
                            $event->getId(),
                            $event->getUserId()
                        );
                    }

                } catch (\Throwable $e) {
                    $failed++;
                    $errors[] = sprintf(
                        'Event #%d (user %d): %s',
                        $event->getId(),
                        $event->getUserId(),
                        $e->getMessage()
                    );

                    $this->logger->error('Failed to replay event', [
                        'event_id' => $event->getId(),
                        'user_id' => $event->getUserId(),
                        'error' => $e->getMessage(),
                    ]);
                }

                $io->progressAdvance();
            }

            $io->progressFinish();

            // Show results
            $io->section('Replay Results');
            $io->table(
                ['Status', 'Count'],
                [
                    ['✓ Successfully published', $success],
                    ['✗ Failed to publish', $failed],
                    ['Total processed', count($events)],
                ]
            );

            // Show errors if any
            if (!empty($errors)) {
                $io->section('Errors');
                $maxErrors = 20;
                $displayErrors = array_slice($errors, 0, $maxErrors);
                
                foreach ($displayErrors as $error) {
                    $io->text('• ' . $error);
                }

                if (count($errors) > $maxErrors) {
                    $io->text(sprintf('... and %d more errors (check logs)', count($errors) - $maxErrors));
                }
            }

            // Log summary
            $this->logger->info('Event replay completed', [
                'total' => count($events),
                'success' => $success,
                'failed' => $failed,
                'user_id' => $userId,
                'since' => $sinceDate->format('c'),
                'unprocessed_only' => $unprocessedOnly,
            ]);

            if ($failed > 0) {
                $io->warning(sprintf(
                    'Replay completed with %d failures. Check logs for details.',
                    $failed
                ));
                return Command::FAILURE;
            }

            $io->success(sprintf('Successfully replayed %d events!', $success));
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Replay failed: ' . $e->getMessage());
            
            $this->logger->error('Event replay command failed', [
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);

            return Command::FAILURE;
        }
    }
}
