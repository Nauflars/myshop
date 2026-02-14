<?php

namespace App\Infrastructure\Controller;

use App\Application\UseCase\AddProductToCart;
use App\Domain\Entity\Cart;
use App\Domain\Entity\CartItem;
use App\Domain\Repository\CartRepositoryInterface;
use App\Domain\Repository\ProductRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/cart')]
class CartController extends AbstractController
{
    public function __construct(
        private readonly CartRepositoryInterface $cartRepository,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly AddProductToCart $addProductToCart,
        private readonly UserRepositoryInterface $userRepository,
    ) {
    }

    #[Route('', name: 'api_cart_view', methods: ['GET'])]
    public function view(Request $request): JsonResponse
    {
        $user = $this->getUser();

        // For non-authenticated users, return empty cart
        if (!$user) {
            return $this->json([
                'items' => [],
                'total' => '$0.00',
                'totalInCents' => 0,
                'itemCount' => 0,
                'totalQuantity' => 0,
            ]);
        }

        $cart = $this->cartRepository->findByUser($user);

        if (!$cart) {
            return $this->json([
                'items' => [],
                'total' => '$0.00',
                'totalInCents' => 0,
                'itemCount' => 0,
                'totalQuantity' => 0,
            ]);
        }

        return $this->json($this->serializeCart($cart));
    }

    #[Route('/items', name: 'api_cart_add_item', methods: ['POST'])]
    public function addItem(Request $request): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->json(['error' => 'Please login to add items to cart'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);

        try {
            $cart = $this->addProductToCart->execute(
                user: $user,
                productId: $data['productId'] ?? '',
                quantity: (int) ($data['quantity'] ?? 1)
            );

            return $this->json($this->serializeCart($cart));
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/items/{productId}', name: 'api_cart_update_item', methods: ['PUT'])]
    public function updateItem(string $productId, Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Please login to update cart'], Response::HTTP_UNAUTHORIZED);
        }

        $cart = $this->cartRepository->findByUser($user);

        if (!$cart) {
            return $this->json(['error' => 'Cart not found'], Response::HTTP_NOT_FOUND);
        }

        $product = $this->productRepository->findById($productId);
        if (!$product) {
            return $this->json(['error' => 'Product not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        $quantity = (int) ($data['quantity'] ?? 1);

        try {
            $item = $cart->findItemByProduct($product);
            if ($item) {
                $item->setQuantity($quantity);
                $this->cartRepository->save($cart);
            }

            return $this->json($this->serializeCart($cart));
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/items/{productId}', name: 'api_cart_remove_item', methods: ['DELETE'])]
    public function removeItem(string $productId): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Please login to remove items'], Response::HTTP_UNAUTHORIZED);
        }

        $cart = $this->cartRepository->findByUser($user);

        if (!$cart) {
            return $this->json(['error' => 'Cart not found'], Response::HTTP_NOT_FOUND);
        }

        $product = $this->productRepository->findById($productId);
        if (!$product) {
            return $this->json(['error' => 'Product not found'], Response::HTTP_NOT_FOUND);
        }

        $cart->removeItemByProduct($product);
        $this->cartRepository->save($cart);

        return $this->json($this->serializeCart($cart));
    }

    #[Route('', name: 'api_cart_clear', methods: ['DELETE'])]
    public function clear(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Please login to clear cart'], Response::HTTP_UNAUTHORIZED);
        }

        $cart = $this->cartRepository->findByUser($user);

        if ($cart) {
            $cart->clear();
            $this->cartRepository->save($cart);
        }

        return $this->json(['message' => 'Cart cleared successfully']);
    }

    private function serializeCart($cart): array
    {
        $items = array_map(function (CartItem $item) {
            return [
                'productId' => $item->getProduct()->getId(),
                'productName' => $item->getProduct()->getName(),
                'quantity' => $item->getQuantity(),
                'price' => $item->getPriceSnapshot()->format(),
                'priceInCents' => $item->getPriceSnapshot()->getAmountInCents(),
                'subtotal' => $item->getSubtotal()->format(),
                'subtotalInCents' => $item->getSubtotal()->getAmountInCents(),
            ];
        }, $cart->getItems()->toArray());

        $total = $cart->calculateTotal();

        return [
            'id' => $cart->getId(),
            'userId' => $cart->getUser()->getId(),
            'items' => $items,
            'total' => $total->format(),
            'totalInCents' => $total->getAmountInCents(),
            'currency' => $total->getCurrency(),
            'itemCount' => $cart->getItemCount(),
            'totalQuantity' => $cart->getTotalQuantity(),
            'updatedAt' => $cart->getUpdatedAt()->format('c'),
        ];
    }
}
