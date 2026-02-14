<?php

declare(strict_types=1);

namespace App\Infrastructure\Controller;

use App\Infrastructure\Repository\DoctrineOrderRepository;
use App\Infrastructure\Repository\DoctrineUserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin controller for viewing user management and insights from spec-006.
 *
 * Implements FR-026 to FR-030: Admin user management
 * - List all registered users with statistics
 * - View user details with order history summary
 * - Search users by email and name
 * - Read-only view (no user modification)
 */
#[Route('/admin/users')]
#[IsGranted('ROLE_ADMIN', message: 'Acceso denegado. Se requiere rol de administrador.')]
class AdminUserController extends AbstractController
{
    public function __construct(
        private readonly DoctrineUserRepository $userRepository,
        private readonly DoctrineOrderRepository $orderRepository,
    ) {
    }

    /**
     * List all registered users with statistics.
     *
     * Implements FR-026: Display list of all users
     * Implements FR-027: Calculate order counts per user
     * Implements FR-030: Search by email and name
     */
    #[Route('', name: 'admin_users_list', methods: ['GET'])]
    public function list(Request $request): Response
    {
        $search = $request->query->get('search', '');

        $queryBuilder = $this->userRepository->createQueryBuilder('u')
            ->orderBy('u.createdAt', 'DESC');

        // FR-030: Search by email or name
        if (!empty($search)) {
            $queryBuilder->andWhere('u.email LIKE :search OR u.name LIKE :search')
                ->setParameter('search', '%'.$search.'%');
        }

        $users = $queryBuilder->getQuery()->getResult();

        // FR-027: Calculate order counts for each user
        $userStats = [];
        foreach ($users as $user) {
            $orderCount = $this->orderRepository->count(['user' => $user, 'status' => 'completed']);

            $userStats[$user->getId()] = [
                'orderCount' => $orderCount,
            ];
        }

        return $this->render('admin/users/list.html.twig', [
            'users' => $users,
            'userStats' => $userStats,
            'currentSearch' => $search,
            'pageTitle' => 'Gestión de Usuarios',
        ]);
    }

    /**
     * View user details with order history summary.
     *
     * Implements FR-028: View user details including order history
     * Implements FR-029: Read-only view (no order modifications)
     */
    #[Route('/{id}', name: 'admin_users_view', methods: ['GET'])]
    public function view(string $id): Response
    {
        $user = $this->userRepository->find($id);

        if (!$user) {
            $this->addFlash('error', 'Usuario no encontrado.');

            return $this->redirectToRoute('admin_users_list');
        }

        // Get user's order history summary (read-only)
        $orders = $this->orderRepository->findBy(
            ['user' => $user],
            ['createdAt' => 'DESC']
        );

        $orderStats = [
            'total' => count($orders),
            'completed' => 0,
            'pending' => 0,
            'cancelled' => 0,
            'totalSpent' => 0.0,
        ];

        foreach ($orders as $order) {
            switch ($order->getStatus()) {
                case 'completed':
                    $orderStats['completed']++;
                    $orderStats['totalSpent'] += $order->getTotal();
                    break;
                case 'pending':
                    $orderStats['pending']++;
                    break;
                case 'cancelled':
                    $orderStats['cancelled']++;
                    break;
            }
        }

        return $this->render('admin/users/view.html.twig', [
            'user' => $user,
            'orders' => $orders,
            'orderStats' => $orderStats,
            'pageTitle' => 'Detalles de Usuario',
        ]);
    }
}
