<?php

namespace App\Infrastructure\Command;

use App\Domain\Entity\Conversation;
use App\Domain\Entity\ConversationMessage;
use App\Domain\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:debug-search-aggregation',
    description: 'Debug search aggregation from conversations',
)]
class DebugSearchAggregationCommand extends Command
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED, 'User email');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = $input->getArgument('email');

        $io->title('Debug Search Aggregation');
        $io->writeln("User: $email");

        // Step 1: Find user
        $io->section('Step 1: Finding user');
        $userRepo = $this->entityManager->getRepository(User::class);
        $user = $userRepo->findOneBy(['email' => $email]);

        if (!$user) {
            $io->error("User not found: $email");

            return Command::FAILURE;
        }

        $io->success('✓ User found: '.$user->getId());

        // Step 2: Query conversations using Conversation::class
        $io->section('Step 2: Querying conversations');
        try {
            $conversationRepo = $this->entityManager->getRepository(Conversation::class);
            $io->writeln('Repository class: '.get_class($conversationRepo));

            $conversations = $conversationRepo->findBy(
                ['user' => $user],
                ['updatedAt' => 'DESC'],
                10
            );

            $io->success('✓ Found '.count($conversations).' conversations');

            if (0 === count($conversations)) {
                $io->warning('No conversations found for this user');

                return Command::SUCCESS;
            }

            // Step 3: Query messages for each conversation
            $io->section('Step 3: Extracting user messages');
            $messageRepo = $this->entityManager->getRepository(ConversationMessage::class);
            $io->writeln('Message repository class: '.get_class($messageRepo));

            $allSearches = [];
            foreach ($conversations as $index => $conversation) {
                $io->writeln("\nConversation ".($index + 1).':');
                $io->writeln('  - ID: '.$conversation->getId());
                $io->writeln('  - Updated: '.$conversation->getUpdatedAt()->format('Y-m-d H:i:s'));

                try {
                    $messages = $messageRepo->findBy(
                        ['conversation' => $conversation, 'role' => 'user'],
                        ['timestamp' => 'DESC'],
                        20
                    );

                    $io->writeln('  - User messages: '.count($messages));

                    foreach ($messages as $msgIndex => $message) {
                        $text = $message->getContent();
                        $textLength = strlen($text);
                        $startsWithSlash = str_starts_with($text, '/');
                        $isValid = $textLength > 3 && !$startsWithSlash;

                        if ($isValid) {
                            $allSearches[] = $text;
                            $io->writeln('    ✓ Msg '.($msgIndex + 1).": \"$text\" (length: $textLength)");
                        } else {
                            $reason = $textLength <= 3 ? 'too short' : 'starts with /';
                            $io->writeln('    ✗ Msg '.($msgIndex + 1).": \"$text\" (length: $textLength, $reason)");
                        }
                    }
                } catch (\Exception $e) {
                    $io->error('Failed to query messages for conversation: '.$e->getMessage());
                    $io->writeln('Exception class: '.get_class($e));
                    $io->writeln('Trace: '.$e->getTraceAsString());
                }
            }

            // Step 4: Show results
            $io->section('Step 4: Aggregation results');
            $uniqueSearches = array_unique($allSearches);
            $recentSearches = array_slice($uniqueSearches, 0, 20);

            $io->writeln('Total messages extracted: '.count($allSearches));
            $io->writeln('Unique searches: '.count($uniqueSearches));
            $io->writeln('After limiting to 20: '.count($recentSearches));

            if (count($recentSearches) > 0) {
                $io->success('✓ Searches found!');
                $io->listing($recentSearches);
            } else {
                $io->warning('No valid searches extracted');
            }
        } catch (\Exception $e) {
            $io->error('Failed to query conversations: '.$e->getMessage());
            $io->writeln('Exception class: '.get_class($e));
            $io->writeln('Trace:');
            $io->writeln($e->getTraceAsString());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
