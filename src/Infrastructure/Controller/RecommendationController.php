<?php

namespace App\Infrastructure\Controller;

use App\Application\Service\RecommendationService;
use App\Domain\Entity\Product;
use App\Domain\Entity\User;
use App\Domain\Repository\ProductRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/recommendations')]
class RecommendationController extends AbstractController
{
    public function __construct(
        private readonly RecommendationService $recommendationService,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly Security $security,
    ) {
    }

    #[Route('', name: 'api_recommendations', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $limit = min(50, max(1, (int) $request->query->get('limit', 10)));

        $user = $this->security->getUser();

        try {
            if ($user instanceof User) {
                // Personalized recommendations for logged-in users
                $result = $this->recommendationService->getRecommendationsForUser($user, $limit);
                $products = $result->getProducts();
            } else {
                // Fallback: return popular/random products for guests
                $products = $this->productRepository->findAll();
                shuffle($products);
                $products = array_slice($products, 0, $limit);
            }
        } catch (\Throwable $e) {
            // Fallback to generic products on any error
            $products = $this->productRepository->findAll();
            shuffle($products);
            $products = array_slice($products, 0, $limit);
        }

        return $this->json([
            'items' => array_map([$this, 'serializeProduct'], $products),
            'total' => count($products),
            'page' => 1,
            'limit' => $limit,
            'hasMore' => false,
        ]);
    }

    private function serializeProduct(Product $product): array
    {
        return [
            'id' => $product->getId(),
            'name' => $product->getName(),
            'description' => $product->getDescription(),
            'price' => [
                'amount' => $product->getPrice()->getAmountInCents(),
                'currency' => $product->getPrice()->getCurrency(),
            ],
            'stock' => $product->getStock(),
            'category' => $product->getCategory(),
            'inStock' => $product->isInStock(),
            'lowStock' => $product->isLowStock(),
            'createdAt' => $product->getCreatedAt()->format('c'),
            'updatedAt' => $product->getUpdatedAt()->format('c'),
        ];
    }
}
