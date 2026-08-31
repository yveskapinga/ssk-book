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
            return new JsonResponse(['error' => ['message' => $exception->getMessage()]], $exception->statusCode);
        }

        return new JsonResponse(['error' => ['message' => 'Une erreur interne est survenue.']], 500);
    }
}
