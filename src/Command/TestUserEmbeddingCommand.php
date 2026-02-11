<?php

declare(strict_types=1);

namespace App\Command;

use App\Application\Message\UpdateUserEmbeddingMessage;
use App\Domain\ValueObject\EventType;
use App\Infrastructure\Queue\RabbitMQPublisher;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-user-embedding',
    description: 'Test publishing user embedding message to queue',
)]
class TestUserEmbeddingCommand extends Command
{
    public function __construct(
        private readonly RabbitMQPublisher $rabbitMQPublisher
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $userId = 'c15e880a-c359-41a9-8fa9-ec844543433b';
        $query = 'laptop para gaming';
        
        $io->info("Publishing search event for user: {$userId}");
        $io->info("Search phrase: {$query}");
        
        try {
            $message = UpdateUserEmbeddingMessage::fromDomainEvent(
                userId: $userId,
                eventType: EventType::SEARCH,
                searchPhrase: $query,
                productId: null,
                occurredAt: new \DateTimeImmutable()
            );
            
            $published = $this->rabbitMQPublisher->publish($message);
            
            if ($published) {
                $io->success('Message published successfully to queue!');
                $io->note("Message ID: {$message->messageId}");
                $io->note("User ID: {$userId}");
                $io->note("Event Type: SEARCH");
                $io->note("Search Phrase: {$query}");
                return Command::SUCCESS;
            } else {
                $io->error('Failed to publish message to queue');
                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $io->error('Error publishing message: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
