<?php

namespace App\Controller;

use App\Book\ReadingService;
use App\Identity\User;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

final class LibraryController extends ApiController
{
    public function __construct(private readonly ReadingService $reading)
    {
    }

    #[Route('/api/library', name: 'api_library', methods: ['GET'])]
    public function catalog(): JsonResponse
    {
        try {
            return $this->success($this->reading->catalog());
        } catch (\Throwable $exception) {
            return $this->failure($exception);
        }
    }

    #[Route('/api/me/dashboard', name: 'api_me_dashboard', methods: ['GET'])]
    public function dashboard(): JsonResponse
    {
        try {
            return $this->success($this->reading->dashboard($this->actor()));
        } catch (\Throwable $exception) {
            return $this->failure($exception);
        }
    }

    #[Route('/api/books/{slug}', name: 'api_book_show', methods: ['GET'])]
    public function show(string $slug): JsonResponse
    {
        try {
            return $this->success($this->reading->book($slug, $this->actor()));
        } catch (\Throwable $exception) {
            return $this->failure($exception);
        }
    }

    #[Route('/api/books/{slug}/chunks', name: 'api_book_chunks', methods: ['GET'])]
    public function chunks(string $slug, Request $request): JsonResponse
    {
        try {
            return $this->success($this->reading->chunks(
                $slug,
                $request->query->getInt('page', 1),
                $request->query->getInt('limit', 5),
                $request->query->getInt('fromPage', 0),
            ));
        } catch (\Throwable $exception) {
            return $this->failure($exception);
        }
    }

    #[Route('/api/books/{slug}/search', name: 'api_book_search', methods: ['GET'])]
    public function search(string $slug, Request $request): JsonResponse
    {
        try {
            return $this->success($this->reading->search($slug, (string) $request->query->get('q', '')));
        } catch (\Throwable $exception) {
            return $this->failure($exception);
        }
    }

    #[Route('/api/books/{slug}/progress', name: 'api_book_progress', methods: ['PATCH'])]
    public function progress(string $slug, Request $request): JsonResponse
    {
        try {
            $payload = $request->toArray();

            $pageNumber = (int) ($payload['pageNumber'] ?? 0);
            if ($pageNumber > 0) {
                return $this->success($this->reading->savePageProgress($this->actor(), $slug, $pageNumber));
            }

            return $this->success($this->reading->saveProgress($this->actor(), $slug, (string) ($payload['chunkId'] ?? '')));
        } catch (\Throwable $exception) {
            return $this->failure($exception);
        }
    }

    #[Route('/api/books/{slug}/bookmarks', name: 'api_book_bookmarks', methods: ['POST'])]
    public function bookmark(string $slug, Request $request): JsonResponse
    {
        try {
            $payload = $request->toArray();

            return $this->success($this->reading->addBookmark($this->actor(), $slug, (string) ($payload['chunkId'] ?? '')), 201);
        } catch (\Throwable $exception) {
            return $this->failure($exception);
        }
    }

    #[Route('/api/me/bookmarks', name: 'api_me_bookmarks', methods: ['GET'])]
    public function myBookmarks(): JsonResponse
    {
        try {
            return $this->success($this->reading->bookmarks($this->actor()));
        } catch (\Throwable $exception) {
            return $this->failure($exception);
        }
    }

    #[Route('/api/bookmarks/{id}', name: 'api_bookmark_delete', methods: ['DELETE'])]
    public function deleteBookmark(string $id): JsonResponse
    {
        try {
            $this->reading->removeBookmark($this->actor(), $id);

            return new JsonResponse(null, 204);
        } catch (\Throwable $exception) {
            return $this->failure($exception);
        }
    }

    #[Route('/api/books/{slug}/notes', name: 'api_book_notes', methods: ['POST'])]
    public function note(string $slug, Request $request): JsonResponse
    {
        try {
            $payload = $request->toArray();

            return $this->success($this->reading->addNote(
                $this->actor(),
                $slug,
                (string) ($payload['chunkId'] ?? ''),
                (string) ($payload['body'] ?? ''),
            ), 201);
        } catch (\Throwable $exception) {
            return $this->failure($exception);
        }
    }

    #[Route('/api/me/notes', name: 'api_me_notes', methods: ['GET'])]
    public function myNotes(): JsonResponse
    {
        try {
            return $this->success($this->reading->notes($this->actor()));
        } catch (\Throwable $exception) {
            return $this->failure($exception);
        }
    }

    #[Route('/api/notes/{id}', name: 'api_note_update', methods: ['PATCH'])]
    public function updateNote(string $id, Request $request): JsonResponse
    {
        try {
            $payload = $request->toArray();
            $this->reading->updateNote($this->actor(), $id, (string) ($payload['body'] ?? ''));

            return new JsonResponse(null, 204);
        } catch (\Throwable $exception) {
            return $this->failure($exception);
        }
    }

    #[Route('/api/notes/{id}', name: 'api_note_delete', methods: ['DELETE'])]
    public function deleteNote(string $id): JsonResponse
    {
        try {
            $this->reading->removeNote($this->actor(), $id);

            return new JsonResponse(null, 204);
        } catch (\Throwable $exception) {
            return $this->failure($exception);
        }
    }

    #[Route('/api/books/{slug}/highlights', name: 'api_book_highlights', methods: ['POST'])]
    public function highlight(string $slug, Request $request): JsonResponse
    {
        try {
            $payload = $request->toArray();

            return $this->success($this->reading->addHighlight(
                $this->actor(),
                $slug,
                (string) ($payload['chunkId'] ?? ''),
                (string) ($payload['excerpt'] ?? ''),
            ), 201);
        } catch (\Throwable $exception) {
            return $this->failure($exception);
        }
    }

    #[Route('/api/me/highlights', name: 'api_me_highlights', methods: ['GET'])]
    public function myHighlights(): JsonResponse
    {
        try {
            return $this->success($this->reading->highlights($this->actor()));
        } catch (\Throwable $exception) {
            return $this->failure($exception);
        }
    }

    #[Route('/api/highlights/{id}', name: 'api_highlight_delete', methods: ['DELETE'])]
    public function deleteHighlight(string $id): JsonResponse
    {
        try {
            $this->reading->removeHighlight($this->actor(), $id);

            return new JsonResponse(null, 204);
        } catch (\Throwable $exception) {
            return $this->failure($exception);
        }
    }

    #[Route('/api/books/{slug}/pages/{pageNumber}', name: 'api_book_page', methods: ['GET'], requirements: ['pageNumber' => '\d+'])]
    public function page(string $slug, int $pageNumber): JsonResponse
    {
        try {
            return $this->success($this->reading->readingPage($slug, $pageNumber));
        } catch (\Throwable $exception) {
            return $this->failure($exception);
        }
    }

    #[Route('/api/books/{slug}/figures/{id}/image', name: 'api_book_figure_image', methods: ['GET'])]
    public function figureImage(string $slug, string $id): BinaryFileResponse|JsonResponse
    {
        try {
            $image = $this->reading->figureImage($slug, $id);
            $response = new BinaryFileResponse($image['path']);
            $response->headers->set('Content-Type', $image['mime']);
            $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, 'figure.jpg');
            $response->setPrivate();

            return $response;
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
