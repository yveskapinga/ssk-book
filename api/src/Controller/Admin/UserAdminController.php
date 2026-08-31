<?php

namespace App\Controller\Admin;

use App\Controller\ApiController;
use App\Identity\AdminUserService;
use App\Identity\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/users', name: 'api_admin_users_')]
final class UserAdminController extends ApiController
{
    public function __construct(private readonly AdminUserService $service)
    {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        try {
            return new JsonResponse(['data' => $this->service->list(
                $request->query->getInt('page', 1),
                $request->query->getInt('limit', 20),
            )]);
        } catch (\Throwable $exception) {
            return $this->failure($exception);
        }
    }

    #[Route('/{id}/status', name: 'status', methods: ['PATCH'])]
    public function status(string $id, Request $request): JsonResponse
    {
        try {
            $actor = $this->getUser();
            if (!$actor instanceof User) {
                throw new \RuntimeException('Authenticated user is unavailable.');
            }
            $payload = $request->toArray();
            $this->service->changeStatus($actor, $id, (string) ($payload['status'] ?? ''));

            return new JsonResponse(null, 204);
        } catch (\Throwable $exception) {
            return $this->failure($exception);
        }
    }
}
