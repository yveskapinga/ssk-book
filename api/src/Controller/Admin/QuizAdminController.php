<?php

namespace App\Controller\Admin;

use App\Book\QuizService;
use App\Controller\ApiController;
use App\Identity\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/quizzes', name: 'api_admin_quizzes_')]
final class QuizAdminController extends ApiController
{
    public function __construct(private readonly QuizService $quizzes)
    {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        try {
            return $this->success($this->quizzes->adminList());
        } catch (\Throwable $exception) {
            return $this->failure($exception);
        }
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        try {
            $payload = $request->toArray();

            return $this->success($this->quizzes->create(
                $this->actor(),
                (string) ($payload['bookSlug'] ?? ''),
                (string) ($payload['title'] ?? ''),
                isset($payload['description']) ? (string) $payload['description'] : null,
                $payload['questions'] ?? [],
            ), 201);
        } catch (\Throwable $exception) {
            return $this->failure($exception);
        }
    }

    #[Route('/{id}/review', name: 'review', methods: ['POST'])]
    public function review(string $id): JsonResponse
    {
        try {
            $this->quizzes->submitForReview($this->actor(), $id);

            return new JsonResponse(null, 204);
        } catch (\Throwable $exception) {
            return $this->failure($exception);
        }
    }

    #[Route('/{id}/publish', name: 'publish', methods: ['POST'])]
    public function publish(string $id): JsonResponse
    {
        try {
            $this->quizzes->publish($this->actor(), $id);

            return new JsonResponse(null, 204);
        } catch (\Throwable $exception) {
            return $this->failure($exception);
        }
    }

    private function actor(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new \RuntimeException('Authenticated user is unavailable.');
        }

        return $user;
    }
}
