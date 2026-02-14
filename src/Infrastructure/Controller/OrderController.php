<?php

namespace App\Infrastructure\Controller;

use App\Application\UseCase\Checkout;
use App\Domain\Entity\Order;
use App\Domain\Entity\OrderItem;
use App\Domain\Repository\OrderRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/orders')]
#[IsGranted('ROLE_CUSTOMER')]
class OrderController extends AbstractController
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly Checkout $checkout,
    ) {
    }

    #[Route('', name: 'api_order_checkout', methods: ['POST'])]
    public function checkout(#[CurrentUser] $user): JsonResponse
    {
        try {
            $order = $this->checkout->execute($user);

            return $this->json($this->serializeOrder($order), Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('', name: 'api_order_list', methods: ['GET'])]
    public function list(#[CurrentUser] $user): JsonResponse
    {
        $orders = $this->orderRepository->findByUser($user);

        return $this->json(array_map([$this, 'serializeOrder'], $orders));
    }

    #[Route('/{orderNumber}', name: 'api_order_show', methods: ['GET'])]
    public function show(string $orderNumber, #[CurrentUser] $user): JsonResponse
    {
        $order = $this->orderRepository->findByOrderNumber($orderNumber);

        if (!$order) {
            return $this->json(['error' => 'Order not found'], Response::HTTP_NOT_FOUND);
        }

        // Ensure user can only see their own orders (unless admin)
        if ($order->getUser()->getId() !== $user->getId() && !$user->isAdmin()) {
            return $this->json(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        return $this->json($this->serializeOrder($order));
    }

    #[Route('/{orderNumber}/status', name: 'api_order_update_status', methods: ['PUT'])]
    #[IsGranted('ROLE_SELLER')]
    public function updateStatus(string $orderNumber, Request $request): JsonResponse
    {
        $order = $this->orderRepository->findByOrderNumber($orderNumber);

        if (!$order) {
            return $this->json(['error' => 'Order not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        $newStatus = $data['status'] ?? '';

        try {
            $order->setStatus($newStatus);
            $this->orderRepository->save($order);

            return $this->json($this->serializeOrder($order));
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    private function serializeOrder(Order $order): array
    {
        $items = array_map(function (OrderItem $item) {
            return [
                'productId' => $item->getProduct()->getId(),
                'productName' => $item->getProductName(),
                'quantity' => $item->getQuantity(),
                'price' => $item->getPrice()->format(),
                'priceInCents' => $item->getPrice()->getAmountInCents(),
                'subtotal' => $item->getSubtotal()->format(),
                'subtotalInCents' => $item->getSubtotal()->getAmountInCents(),
            ];
        }, $order->getItems()->toArray());

        return [
            'id' => $order->getId(),
            'orderNumber' => $order->getOrderNumber(),
            'userId' => $order->getUser()->getId(),
            'items' => $items,
            'total' => $order->getTotal()->format(),
            'totalInCents' => $order->getTotal()->getAmountInCents(),
            'currency' => $order->getTotal()->getCurrency(),
            'status' => $order->getStatus(),
            'createdAt' => $order->getCreatedAt()->format('c'),
            'updatedAt' => $order->getUpdatedAt()->format('c'),
        ];
    }
}
