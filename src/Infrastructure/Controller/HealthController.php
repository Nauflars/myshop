<?php

namespace App\Infrastructure\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Annotation\Route;

class HealthController extends AbstractController
{
    public function __construct(
        private readonly Connection $connection
    ) {
    }

    #[Route('/health', name: 'health_check', methods: ['GET'])]
    public function check(): JsonResponse
    {
        $health = [
            'status' => 'ok',
            'symfony_version' => Kernel::VERSION,
            'php_version' => PHP_VERSION,
            'environment' => $this->getParameter('kernel.environment'),
            'database' => 'disconnected',
        ];

        try {
            $this->connection->executeQuery('SELECT 1');
            $health['database'] = 'connected';
        } catch (\Exception $e) {
            $health['status'] = 'degraded';
            $health['database_error'] = $e->getMessage();
        }

        $statusCode = $health['status'] === 'ok' ? 200 : 503;

        return $this->json($health, $statusCode);
    }
}
