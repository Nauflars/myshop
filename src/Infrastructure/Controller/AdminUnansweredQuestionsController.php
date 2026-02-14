<?php

declare(strict_types=1);

namespace App\Infrastructure\Controller;

use App\Domain\Entity\UnansweredQuestion;
use App\Infrastructure\Repository\UnansweredQuestionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin controller for managing unanswered questions from spec-006.
 *
 * Implements FR-013 to FR-018: Admin unanswered questions management
 * - List all unanswered questions with filtering
 * - Update question status (New → Reviewed → Planned → Resolved)
 * - Add internal admin notes
 * - Pagination support (50 per page)
 */
#[Route('/admin/unanswered-questions')]
#[IsGranted('ROLE_ADMIN', message: 'Acceso denegado. Se requiere rol de administrador.')]
class AdminUnansweredQuestionsController extends AbstractController
{
    public function __construct(
        private readonly UnansweredQuestionRepository $questionRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * List all unanswered questions with filtering and pagination.
     *
     * Implements FR-013: Display list of all unanswered questions
     * Implements FR-016: Filter by status and date range
     * Implements FR-017: Pagination (50 per page)
     */
    #[Route('', name: 'admin_unanswered_questions_list', methods: ['GET'])]
    public function list(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 50;
        $offset = ($page - 1) * $limit;

        // Build filter criteria from query parameters
        $criteria = [];

        if ($status = $request->query->get('status')) {
            $criteria['status'] = $status;
        }

        if ($reason = $request->query->get('reason')) {
            $criteria['reason'] = $reason;
        }

        $criteria['limit'] = $limit;
        $criteria['offset'] = $offset;

        // Get questions with filters applied
        $questions = $this->questionRepository->findWithFilters($criteria);
        $totalQuestions = $this->questionRepository->countTotal();
        $totalPages = (int) ceil($totalQuestions / $limit);

        // Get status and reason counts for filter UI
        $statusCounts = $this->questionRepository->countByStatus();
        $reasonCounts = $this->questionRepository->countByReason();

        return $this->render('admin/unanswered_questions/list.html.twig', [
            'questions' => $questions,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalQuestions' => $totalQuestions,
            'statusCounts' => $statusCounts,
            'reasonCounts' => $reasonCounts,
            'currentFilters' => [
                'status' => $status,
                'reason' => $reason,
            ],
            'pageTitle' => 'Preguntas Sin Respuesta',
        ]);
    }

    /**
     * View and update a specific unanswered question.
     *
     * Implements FR-014: Change question status
     * Implements FR-015: Add internal notes
     */
    #[Route('/{id}', name: 'admin_unanswered_questions_view', methods: ['GET', 'POST'])]
    public function view(Request $request, int $id): Response
    {
        $question = $this->questionRepository->find($id);

        if (!$question) {
            $this->addFlash('error', 'Pregunta no encontrada.');

            return $this->redirectToRoute('admin_unanswered_questions_list');
        }

        if ($request->isMethod('POST')) {
            // Update status if provided
            if ($newStatus = $request->request->get('status')) {
                if (in_array($newStatus, [
                    UnansweredQuestion::STATUS_NEW,
                    UnansweredQuestion::STATUS_REVIEWED,
                    UnansweredQuestion::STATUS_PLANNED,
                    UnansweredQuestion::STATUS_RESOLVED,
                ])) {
                    $question->setStatus($newStatus);
                }
            }

            // Update admin notes if provided
            if ($request->request->has('admin_notes')) {
                $question->setAdminNotes($request->request->get('admin_notes'));
            }

            $this->entityManager->flush();
            $this->addFlash('success', 'Pregunta actualizada correctamente.');

            return $this->redirectToRoute('admin_unanswered_questions_view', ['id' => $id]);
        }

        return $this->render('admin/unanswered_questions/view.html.twig', [
            'question' => $question,
            'pageTitle' => 'Detalles de Pregunta',
            'availableStatuses' => [
                UnansweredQuestion::STATUS_NEW => 'Nueva',
                UnansweredQuestion::STATUS_REVIEWED => 'Revisada',
                UnansweredQuestion::STATUS_PLANNED => 'Planificada',
                UnansweredQuestion::STATUS_RESOLVED => 'Resuelta',
            ],
        ]);
    }

    /**
     * Bulk update status for multiple questions.
     *
     * Implements FR-018 (P3): Bulk operations on questions
     */
    #[Route('/bulk/update-status', name: 'admin_unanswered_questions_bulk_update', methods: ['POST'])]
    public function bulkUpdateStatus(Request $request): Response
    {
        $questionIds = $request->request->all('question_ids') ?? [];
        $newStatus = $request->request->get('status');

        if (empty($questionIds) || !$newStatus) {
            $this->addFlash('error', 'Seleccione preguntas y un estado válido.');

            return $this->redirectToRoute('admin_unanswered_questions_list');
        }

        $count = 0;
        foreach ($questionIds as $id) {
            $question = $this->questionRepository->find((int) $id);
            if ($question) {
                $question->setStatus($newStatus);
                ++$count;
            }
        }

        $this->entityManager->flush();
        $this->addFlash('success', sprintf('%d pregunta(s) actualizada(s) correctamente.', $count));

        return $this->redirectToRoute('admin_unanswered_questions_list');
    }
}
