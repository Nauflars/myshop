<?php

namespace App\Infrastructure\Controller;

use App\Application\Message\UpdateUserEmbeddingMessage;
use App\Application\Service\ErrorMessageTranslator;
use App\Application\Service\SearchFacade;
use App\Application\UseCase\SearchProduct;
use App\Domain\Entity\Product;
use App\Domain\Entity\User;
use App\Domain\Repository\ProductRepositoryInterface;
use App\Domain\ValueObject\EventType;
use App\Domain\ValueObject\Money;
use App\Domain\ValueObject\SearchQuery;
use App\Entity\SearchHistory;
use App\Infrastructure\Queue\RabbitMQPublisher;
use App\Repository\SearchHistoryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
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
        private readonly SearchProduct $searchProduct,
        private readonly SearchFacade $searchFacade,
        private readonly ErrorMessageTranslator $errorTranslator,
        private readonly Security $security,
        private readonly SearchHistoryRepository $searchHistoryRepository,
        private readonly RabbitMQPublisher $rabbitMQPublisher,
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

        // Update user profile automatically after search (spec-013 auto-update)
        $user = $this->security->getUser();
        if ($user instanceof User && !empty($query)) {
            try {
                // Save search history
                $searchHistory = new SearchHistory($user, $query, 'keyword', $category);
                $this->searchHistoryRepository->save($searchHistory);
            } catch (\Exception $e) {
                // Log but don't fail - search history is non-critical
                error_log('Search history save error: '.$e->getMessage());
            }
        }

        return $this->json(array_map([$this, 'serializeProduct'], $products));
    }

    /**
     * Semantic/Keyword search endpoint.
     *
     * Implements spec-010 T044-T047: Search with mode parameter and fallback handling
     *
     * @example GET /api/products/search?q=laptop for gaming&mode=semantic&limit=10
     * @example GET /api/products/search?q=phone&mode=keyword& category=Electronics
     */
    #[Route('/search', name: 'api_product_search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $query = $request->query->get('q', '');
        $mode = $request->query->get('mode', 'semantic');
        $limit = (int) $request->query->get('limit', 10);
        $offset = (int) $request->query->get('offset', 0);
        $minSimilarity = (float) $request->query->get('min_similarity', 0.3);
        $category = $request->query->get('category');

        // DEBUG: Force explicit response for testing
        $debug = $request->query->get('debug', false);

        if (empty($query)) {
            return $this->json([
                'error' => 'Query parameter "q" is required',
                'example' => '/api/products/search?q=laptop&mode=semantic',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $searchQuery = new SearchQuery(
                query: $query,
                limit: $limit,
                offset: $offset,
                minSimilarity: $minSimilarity,
                category: $category
            );

            $result = $this->searchFacade->search($searchQuery, $mode);

            // Update user profile automatically after search (spec-013 auto-update)
            $user = $this->security->getUser();
            if ($user instanceof User && !empty($query)) {
                try {
                    // Save search history
                    $searchHistory = new SearchHistory($user, $query, $mode, $category);
                    $this->searchHistoryRepository->save($searchHistory);

                    // spec-014: Publish search event to queue for user embedding update
                    $message = UpdateUserEmbeddingMessage::fromDomainEvent(
                        userId: $user->getId(),
                        eventType: EventType::SEARCH,
                        searchPhrase: $query,
                        productId: null,
                        occurredAt: new \DateTimeImmutable()
                    );
                    $this->rabbitMQPublisher->publish($message);
                } catch (\Exception $e) {
                    // Log but don't fail - profile update is non-critical
                    error_log('Profile update after product search error: '.$e->getMessage());
                }
            }

            return $this->json($result->toArray());
        } catch (\InvalidArgumentException $e) {
            // T094: User-friendly validation errors (expose message as it's user-facing)
            $errorData = $this->errorTranslator->translateWithStatus($e, 'search');

            return $this->json([
                'error' => $errorData['message'],
            ], $errorData['status_code']);
        } catch (\Exception $e) {
            // T094: User-friendly error messages (hide technical details)
            $errorData = $this->errorTranslator->translateWithStatus($e, 'search');

            return $this->json([
                'error' => $errorData['message'],
            ], $errorData['status_code']);
        }
    }

    #[Route('/{id}', name: 'api_product_show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $product = $this->productRepository->findById($id);

        if (!$product) {
            return $this->json(['error' => 'Product not found'], Response::HTTP_NOT_FOUND);
        }

        // spec-014: Publish product view event for authenticated users
        $user = $this->security->getUser();
        if ($user instanceof User) {
            try {
                $message = UpdateUserEmbeddingMessage::fromDomainEvent(
                    userId: $user->getId(),
                    eventType: EventType::PRODUCT_VIEW,
                    searchPhrase: null,
                    productId: (int) $product->getId(),
                    occurredAt: new \DateTimeImmutable()
                );
                $this->rabbitMQPublisher->publish($message);
            } catch (\Exception $e) {
                // Log but don't fail - event publishing is non-critical for user experience
                error_log('Failed to publish product view event: '.$e->getMessage());
            }
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
