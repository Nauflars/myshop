<?php

declare(strict_types=1);

namespace App\Infrastructure\Controller;

use App\Domain\Entity\Product;
use App\Domain\ValueObject\Money;
use App\Infrastructure\Repository\DoctrineProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin controller for managing products from spec-006.
 *
 * Implements FR-019 to FR-025: Admin product management
 * - List all products with sorting and filtering
 * - Create new products
 * - Edit existing products
 * - Delete products with confirmation
 * - Data validation (positive price, non-negative stock)
 */
#[Route('/admin/products')]
#[IsGranted('ROLE_ADMIN', message: 'Acceso denegado. Se requiere rol de administrador.')]
class AdminProductController extends AbstractController
{
    public function __construct(
        private readonly DoctrineProductRepository $productRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * List all products with sorting and filtering.
     *
     * Implements FR-019: Display list of all products
     * Implements FR-023: Sort by name, price, stock, category
     * Implements FR-024: Filter by category and availability
     */
    #[Route('', name: 'admin_products_list', methods: ['GET'])]
    public function list(Request $request): Response
    {
        $sortBy = $request->query->get('sort', 'name');
        $sortOrder = $request->query->get('order', 'ASC');
        $category = $request->query->get('category');

        $queryBuilder = $this->productRepository->createQueryBuilder('p');

        // Apply category filter
        if ($category) {
            $queryBuilder->andWhere('p.category = :category')
                ->setParameter('category', $category);
        }

        // Apply sorting
        $allowedSortFields = ['name', 'price', 'stock', 'category'];
        if (in_array($sortBy, $allowedSortFields)) {
            $queryBuilder->orderBy('p.'.$sortBy, 'DESC' === strtoupper($sortOrder) ? 'DESC' : 'ASC');
        }

        $products = $queryBuilder->getQuery()->getResult();

        return $this->render('admin/products/list.html.twig', [
            'products' => $products,
            'currentSort' => $sortBy,
            'currentOrder' => $sortOrder,
            'currentCategory' => $category,
            'pageTitle' => 'Gestión de Productos',
        ]);
    }

    /**
     * Show form to create a new product.
     *
     * Implements FR-020: Create new product with required fields
     */
    #[Route('/create', name: 'admin_products_create', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            // Validate required fields
            $name = trim($request->request->get('name', ''));
            $price = $request->request->get('price');
            $stock = $request->request->get('stock');
            $description = trim($request->request->get('description', ''));
            $category = trim($request->request->get('category', ''));

            $errors = [];

            if (empty($name)) {
                $errors[] = 'El nombre es obligatorio.';
            }

            // FR-025: Validate positive price
            if (!is_numeric($price) || (float) $price <= 0) {
                $errors[] = 'El precio debe ser un número positivo.';
            }

            // FR-025: Validate non-negative stock
            if (!is_numeric($stock) || (int) $stock < 0) {
                $errors[] = 'El stock debe ser un número no negativo.';
            }

            if (empty($description)) {
                $errors[] = 'La descripción es obligatoria.';
            }

            if (empty($category)) {
                $errors[] = 'La categoría es obligatoria.';
            }

            if (empty($errors)) {
                $product = new Product(
                    name: $name,
                    description: $description,
                    price: new Money((int) ((float) $price * 100), 'USD'),
                    stock: (int) $stock,
                    category: $category
                );

                $this->entityManager->persist($product);
                $this->entityManager->flush();

                $this->addFlash('success', 'Producto creado correctamente.');

                return $this->redirectToRoute('admin_products_list');
            }
            foreach ($errors as $error) {
                $this->addFlash('error', $error);
            }
        }

        return $this->render('admin/products/create.html.twig', [
            'pageTitle' => 'Crear Producto',
        ]);
    }

    /**
     * Show form to edit an existing product.
     *
     * Implements FR-021: Edit product details
     */
    #[Route('/{id}/edit', name: 'admin_products_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, string $id): Response
    {
        $product = $this->productRepository->find($id);

        if (!$product) {
            $this->addFlash('error', 'Producto no encontrado.');

            return $this->redirectToRoute('admin_products_list');
        }

        if ($request->isMethod('POST')) {
            $name = trim($request->request->get('name', ''));
            $price = $request->request->get('price');
            $stock = $request->request->get('stock');
            $description = trim($request->request->get('description', ''));
            $category = trim($request->request->get('category', ''));

            $errors = [];

            if (empty($name)) {
                $errors[] = 'El nombre es obligatorio.';
            }

            if (!is_numeric($price) || (float) $price <= 0) {
                $errors[] = 'El precio debe ser un número positivo.';
            }

            if (!is_numeric($stock) || (int) $stock < 0) {
                $errors[] = 'El stock debe ser un número no negativo.';
            }

            if (empty($description)) {
                $errors[] = 'La descripción es obligatoria.';
            }

            if (empty($category)) {
                $errors[] = 'La categoría es obligatoria.';
            }

            if (empty($errors)) {
                $product->setName($name);
                $product->setPrice(new Money((int) ((float) $price * 100), 'USD'));
                $product->setStock((int) $stock);
                $product->setDescription($description);
                $product->setCategory($category);

                $this->entityManager->flush();

                $this->addFlash('success', 'Producto actualizado correctamente.');

                return $this->redirectToRoute('admin_products_list');
            }
            foreach ($errors as $error) {
                $this->addFlash('error', $error);
            }
        }

        return $this->render('admin/products/edit.html.twig', [
            'product' => $product,
            'pageTitle' => 'Editar Producto',
        ]);
    }

    /**
     * Delete a product with confirmation.
     *
     * Implements FR-022: Delete product with confirmation dialog
     */
    #[Route('/{id}/delete', name: 'admin_products_delete', methods: ['POST'])]
    public function delete(Request $request, string $id): Response
    {
        $product = $this->productRepository->find($id);

        if (!$product) {
            $this->addFlash('error', 'Producto no encontrado.');

            return $this->redirectToRoute('admin_products_list');
        }

        // Verify CSRF token for delete action
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('delete-product-'.$id, $token)) {
            $this->addFlash('error', 'Token de seguridad inválido.');

            return $this->redirectToRoute('admin_products_list');
        }

        $productName = $product->getName();
        $this->entityManager->remove($product);
        $this->entityManager->flush();

        $this->addFlash('success', sprintf('Producto "%s" eliminado correctamente.', $productName));

        return $this->redirectToRoute('admin_products_list');
    }
}
