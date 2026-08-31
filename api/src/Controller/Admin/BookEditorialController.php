<?php

namespace App\Controller\Admin;

use App\Book\EditorialService;
use App\Book\EmbeddingService;
use App\Controller\ApiController;
use App\Identity\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/book-versions', name: 'api_admin_book_versions_')]
final class BookEditorialController extends ApiController
{
    public function __construct(private readonly EditorialService $editorial, private readonly EmbeddingService $embeddings) {}

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        try { return $this->success($this->editorial->list($request->query->getInt('page', 1), $request->query->getInt('limit', 20))); }
        catch (\Throwable $exception) { return $this->failure($exception); }
    }

    #[Route('/{id}/chunks', name: 'chunks', methods: ['GET'])]
    public function chunks(string $id, Request $request): JsonResponse
    {
        try { return $this->success($this->editorial->chunks($id, $request->query->getInt('page', 1), $request->query->getInt('limit', 30))); }
        catch (\Throwable $exception) { return $this->failure($exception); }
    }

    #[Route('/{id}/embeddings', name: 'embeddings', methods: ['POST'])]
    public function embeddings(string $id): JsonResponse
    {
        try { return $this->success($this->embeddings->index($this->actor(), $id)); }
        catch (\Throwable $exception) { return $this->failure($exception); }
    }

    #[Route('/{id}/review', name: 'review', methods: ['POST'])]
    public function review(string $id, Request $request): JsonResponse
    {
        try {
            $payload = $request->toArray();
            $this->editorial->decide($this->actor(), $id, (string) ($payload['decision'] ?? ''), isset($payload['notes']) ? (string) $payload['notes'] : null);
            return new JsonResponse(null, 204);
        } catch (\Throwable $exception) { return $this->failure($exception); }
    }

    #[Route('/{id}/publish', name: 'publish', methods: ['POST'])]
    public function publish(string $id): JsonResponse
    {
        try { $this->editorial->publish($this->actor(), $id); return new JsonResponse(null, 204); }
        catch (\Throwable $exception) { return $this->failure($exception); }
    }

    private function actor(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) throw new \RuntimeException('Authenticated user is unavailable.');
        return $user;
    }
}
