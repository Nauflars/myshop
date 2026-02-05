<?php

namespace App\Infrastructure\Controller;

use App\Application\UseCase\SearchProduct;
use App\Domain\Entity\Product;
use App\Domain\Repository\ProductRepositoryInterface;
use App\Domain\ValueObject\Money;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/products')]
class ProductController extends AbstractController
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly SearchProduct $searchProduct
    ) {
    }

    #[Route('', name: 'api_product_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $query = $request->query->get('q');
        $category = $request->query->get('category');
        $minPrice = $request->query->get('minPrice');
        $maxPrice = $request->query->get('maxPrice');

        $products = $this->searchProduct->execute(
            query: $query,
            category: $category,
            minPrice: $minPrice ? (int) ($minPrice * 100) : null,
            maxPrice: $maxPrice ? (int) ($maxPrice * 100) : null
        );

        return $this->json(array_map([$this, 'serializeProduct'], $products));
    }

    #[Route('/{id}', name: 'api_product_show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $product = $this->productRepository->findById($id);

        if (!$product) {
            return $this->json(['error' => 'Product not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serializeProduct($product));
    }

    #[Route('', name: 'api_product_create', methods: ['POST'])]
    #[IsGranted('ROLE_SELLER')]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        try {
            $product = new Product(
                name: $data['name'] ?? '',
                description: $data['description'] ?? '',
                price: Money::fromDecimal((float) ($data['price'] ?? 0)),
                stock: (int) ($data['stock'] ?? 0),
                category: $data['category'] ?? ''
            );

            $this->productRepository->save($product);

            return $this->json($this->serializeProduct($product), Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}', name: 'api_product_update', methods: ['PUT'])]
    #[IsGranted('ROLE_SELLER')]
    public function update(string $id, Request $request): JsonResponse
    {
        $product = $this->productRepository->findById($id);

        if (!$product) {
            return $this->json(['error' => 'Product not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        try {
            if (isset($data['name'])) {
                $product->setName($data['name']);
            }
            if (isset($data['description'])) {
                $product->setDescription($data['description']);
            }
            if (isset($data['price'])) {
                $product->setPrice(Money::fromDecimal((float) $data['price']));
            }
            if (isset($data['stock'])) {
                $product->setStock((int) $data['stock']);
            }
            if (isset($data['category'])) {
                $product->setCategory($data['category']);
            }

            $this->productRepository->save($product);

            return $this->json($this->serializeProduct($product));
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}', name: 'api_product_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(string $id): JsonResponse
    {
        $product = $this->productRepository->findById($id);

        if (!$product) {
            return $this->json(['error' => 'Product not found'], Response::HTTP_NOT_FOUND);
        }

        try {
            $this->productRepository->delete($product);
            return $this->json(['message' => 'Product deleted successfully']);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    private function serializeProduct(Product $product): array
    {
        return [
            'id' => $product->getId(),
            'name' => $product->getName(),
            'description' => $product->getDescription(),
            'price' => $product->getPrice()->format(),
            'priceInCents' => $product->getPrice()->getAmountInCents(),
            'currency' => $product->getPrice()->getCurrency(),
            'stock' => $product->getStock(),
            'category' => $product->getCategory(),
            'inStock' => $product->isInStock(),
            'lowStock' => $product->isLowStock(),
            'createdAt' => $product->getCreatedAt()->format('c'),
            'updatedAt' => $product->getUpdatedAt()->format('c'),
        ];
    }
}
