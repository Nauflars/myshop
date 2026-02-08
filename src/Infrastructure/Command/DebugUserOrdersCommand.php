<?php

namespace App\Infrastructure\Command;

use App\Domain\Entity\User;
use App\Domain\Entity\Order;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:debug:user-orders',
    description: 'Debug user orders and purchases'
)]
class DebugUserOrdersCommand extends Command
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
    }

    protected function configure(): void
    {
        $this->addOption(
            'user-id',
            'u',
            InputOption::VALUE_REQUIRED,
            'User ID to debug'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $userId = $input->getOption('user-id');

        if (!$userId) {
            $io->error('Please provide --user-id');
            return Command::INVALID;
        }

        $user = $this->entityManager->getRepository(User::class)->find($userId);

        if (!$user) {
            $io->error("User not found: {$userId}");
            return Command::FAILURE;
        }

        $io->title("Debugging orders for user: {$user->getName()}");

        // Query orders using repository
        $orderRepository = $this->entityManager->getRepository(Order::class);
        $orders = $orderRepository->findBy(['user' => $user]);

        $io->section("Total orders: " . count($orders));

        foreach ($orders as $order) {
            $io->writeln("Order {$order->getOrderNumber()} - Status: {$order->getStatus()}");
            
            foreach ($order->getItems() as $item) {
                $product = $item->getProduct();
                $io->writeln("  - {$product->getName()} (x{$item->getQuantity()})");
            }
        }

        // Filter shipped/delivered only
        $shippedOrders = array_filter($orders, function($order) {
            $status = $order->getStatus();
            return $status === Order::STATUS_DELIVERED || $status === Order::STATUS_SHIPPED;
        });

        $io->section("Shipped/Delivered orders: " . count($shippedOrders));

        foreach ($shippedOrders as $order) {
            $io->writeln("Order {$order->getOrderNumber()} - Status: {$order->getStatus()}");
            
            foreach ($order->getItems() as $item) {
                $product = $item->getProduct();
                $io->writeln("  - {$product->getName()} (x{$item->getQuantity()})");
            }
        }

        return Command::SUCCESS;    }
}