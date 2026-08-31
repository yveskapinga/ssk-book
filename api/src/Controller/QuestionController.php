<?php

namespace App\Controller;

use App\Book\QuestionService;
use App\Identity\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class QuestionController extends ApiController
{
    #[Route('/api/books/{slug}/questions', name: 'api_book_question', methods: ['POST'])]
    public function __invoke(string $slug, Request $request, QuestionService $service): JsonResponse
    {
        try {
            $user = $this->getUser();
            if (!$user instanceof User) throw new \RuntimeException('Authenticated user is unavailable.');
            $payload = $request->toArray();
            return $this->success($service->ask($user, $slug, (string) ($payload['question'] ?? ''), isset($payload['conversationId']) ? (string) $payload['conversationId'] : null));
        } catch (\Throwable $exception) { return $this->failure($exception); }
    }
}
