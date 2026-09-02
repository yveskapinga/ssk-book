<?php

namespace App\Controller;

use App\Book\QuizService;
use App\Identity\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class QuizController extends ApiController
{
    public function __construct(private readonly QuizService $quizzes)
    {
    }

    #[Route('/api/quizzes', name: 'api_quizzes', methods: ['GET'])]
    public function list(): JsonResponse
    {
        try {
            return $this->success($this->quizzes->published());
        } catch (\Throwable $exception) {
            return $this->failure($exception);
        }
    }

    #[Route('/api/quizzes/{id}', name: 'api_quiz_show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        try {
            return $this->success($this->quizzes->readerDetail($id));
        } catch (\Throwable $exception) {
            return $this->failure($exception);
        }
    }

    #[Route('/api/quizzes/{id}/attempts', name: 'api_quiz_start', methods: ['POST'])]
    public function start(string $id): JsonResponse
    {
        try {
            return $this->success($this->quizzes->startAttempt($this->actor(), $id), 201);
        } catch (\Throwable $exception) {
            return $this->failure($exception);
        }
    }

    #[Route('/api/quiz-attempts/{id}', name: 'api_quiz_submit', methods: ['POST'])]
    public function submit(string $id, Request $request): JsonResponse
    {
        try {
            $payload = $request->toArray();

            return $this->success($this->quizzes->submit($this->actor(), $id, $payload['answers'] ?? []));
        } catch (\Throwable $exception) {
            return $this->failure($exception);
        }
    }

    #[Route('/api/me/quiz-attempts', name: 'api_me_quiz_attempts', methods: ['GET'])]
    public function history(): JsonResponse
    {
        try {
            return $this->success($this->quizzes->history($this->actor()));
        } catch (\Throwable $exception) {
            return $this->failure($exception);
        }
    }

    #[Route('/api/quizzes/{id}/ranking', name: 'api_quiz_ranking', methods: ['GET'])]
    public function ranking(string $id): JsonResponse
    {
        try {
            return $this->success($this->quizzes->ranking($id));
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
