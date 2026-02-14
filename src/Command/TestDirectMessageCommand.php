<?php

declare(strict_types=1);

namespace App\Command;

use App\Application\Message\UpdateUserEmbeddingMessage;
use App\Domain\ValueObject\EventType;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

#[AsCommand(
    name: 'app:test-direct-message',
    description: 'Test direct message dispatch to RabbitMQ'
)]
final class TestDirectMessageCommand extends Command
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Creating test message...');

        $message = UpdateUserEmbeddingMessage::fromDomainEvent(
            userId: '999',
            eventType: EventType::SEARCH,
            searchPhrase: 'test search query',
            productId: null,
            occurredAt: new \DateTimeImmutable(),
            metadata: ['test' => true]
        );

        $output->writeln('Dispatching message with TransportNamesStamp...');

        try {
            $this->messageBus->dispatch($message, [
                new TransportNamesStamp(['user_embedding_updates']),
            ]);

            $output->writeln('<info>✅ Message dispatched successfully!</info>');
        } catch (\Throwable $e) {
            $output->writeln('<error>❌ Error: '.$e->getMessage().'</error>');
            $output->writeln('<error>   '.$e->getTraceAsString().'</error>');

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
