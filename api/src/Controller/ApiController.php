<?php

namespace App\Controller;

use App\Shared\DomainException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;

abstract class ApiController extends AbstractController
{
    protected function success(array $data = [], int $status = 200): JsonResponse
    {
        return new JsonResponse(['data' => $data], $status);
    }

    protected function failure(\Throwable $exception): JsonResponse
    {
        if ($exception instanceof DomainException) {
            return new JsonResponse([
                'error' => [
                    'code' => 'DOMAIN_ERROR',
                    'message' => $exception->getMessage(),
                    'details' => null,
                    'correlationId' => null,
                ],
            ], $exception->statusCode);
        }

        $correlationId = bin2hex(random_bytes(8));
        error_log(sprintf(
            '[ssk-book %s] %s: %s in %s:%d',
            $correlationId,
            $exception::class,
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
        ));

        return new JsonResponse([
            'error' => [
                'code' => 'INTERNAL_ERROR',
                'message' => 'Une erreur interne est survenue.',
                'details' => null,
                'correlationId' => $correlationId,
            ],
        ], 500);
    }
}
