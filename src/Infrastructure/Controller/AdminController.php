<?php

declare(strict_types=1);

namespace App\Infrastructure\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Base admin controller for spec-006: Unanswered Questions Tracking & Admin Panel.
 *
 * Provides common functionality for all admin controllers.
 * All routes require ROLE_ADMIN access.
 */
#[Route('/admin')]
#[IsGranted('ROLE_ADMIN', message: 'Acceso denegado. Se requiere rol de administrador.')]
class AdminController extends AbstractController
{
    /**
     * Admin dashboard home page.
     *
     * Displays summary cards with:
     * - Total unanswered questions by status
     * - Recent unanswered questions (last 10)
     * - Product count and low stock alerts
     * - User registration stats
     */
    #[Route('', name: 'admin_home', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/index.html.twig', [
            'pageTitle' => 'Panel de Administración',
        ]);
    }
}
