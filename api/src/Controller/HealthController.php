<?php

namespace App\Controller;

use App\System\HealthService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class HealthController
{
    #[Route('/api/health', name: 'api_health', methods: ['GET'])]
    public function __invoke(HealthService $service): JsonResponse
    {
        try {
            $status = $service->status();
            $httpStatus = $status['httpStatus'];
            unset($status['httpStatus']);

            return new JsonResponse($status, $httpStatus);
        } catch (\Throwable) {
            return new JsonResponse([
                'service' => 'ssk-book-api',
                'status' => 'degraded',
                'database' => 'unknown',
            ], 503);
        }
    }
}
