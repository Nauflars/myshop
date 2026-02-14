<?php

declare(strict_types=1);

namespace App\Infrastructure\Controller;

use App\Application\Service\SearchMetricsCollector;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * AdminMetricsController - Display search performance metrics dashboard.
 *
 * Implements T089: Admin dashboard widget showing search metrics
 */
class AdminMetricsController extends AbstractController
{
    public function __construct(
        private readonly SearchMetricsCollector $metricsCollector,
    ) {
    }

    /**
     * Search metrics dashboard.
     */
    #[Route('/admin/search-metrics', name: 'admin_search_metrics', methods: ['GET'])]
    public function searchMetrics(): Response
    {
        // Get comprehensive metrics summary
        $metrics = $this->metricsCollector->getMetricsSummary();

        return $this->render('admin/search_metrics_dashboard.html.twig', [
            'metrics' => $metrics,
        ]);
    }
}
