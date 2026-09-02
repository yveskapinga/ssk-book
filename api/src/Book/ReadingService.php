<?php

namespace App\Book;

use App\Identity\User;
use App\Shared\DomainException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\Uid\Uuid;

final readonly class ReadingService
{
    public function __construct(
        private BookRepository $books,
        private ReadingRepository $reading,
        private PageImageRepository $images,
    ) {
    }

    public function catalog(): array
    {
        return ['items' => $this->books->publishedCatalog()];
    }

    public function book(string $slug, User $user): array
    {
        $book = $this->requirePublished($slug);
        $versionId = $book['version_id'];
        $total = $this->books->chunkCount($versionId);

        return [
            'book' => $book,
            'toc' => $this->books->tableOfContents($versionId),
            'progress' => $this->reading->progress($user->id, $versionId),
            'chunkCount' => $total,
        ];
    }

    public function chunks(string $slug, int $page, int $limit, int $fromPage = 0): array
    {
        $book = $this->requirePublished($slug);
        $limit = max(1, min(20, $limit));
        $versionId = $book['version_id'];
        if ($fromPage > 0) {
            $position = $this->books->chunkPositionAtPage($versionId, $fromPage);
            $page = null === $position ? 1 : $position + 1;
        }
        $page = max(1, $page);
        $items = $this->books->publishedChunks($versionId, $limit, ($page - 1) * $limit);

        return [
            'items' => $items,
            'page' => $page,
            'limit' => $limit,
            'total' => $this->books->chunkCount($versionId),
        ];
    }

    public function search(string $slug, string $query): array
    {
        $query = trim($query);
        if (mb_strlen($query) < 2) {
            throw new DomainException('La recherche doit contenir au moins 2 caractères.');
        }
        $book = $this->requirePublished($slug);

        return ['items' => $this->books->searchPublished($book['version_id'], $query, 30)];
    }

    public function readingPage(string $slug, int $pageNumber): array
    {
        $book = $this->requirePublished($slug);
        $pageCount = (int) ($book['page_count'] ?? 0);
        if ($pageNumber < 1 || $pageNumber > $pageCount) {
            throw new DomainException('Page introuvable.', 404);
        }
        $page = $this->books->publishedPage($book['version_id'], $pageNumber);
        if (null === $page) {
            throw new DomainException('Page introuvable.', 404);
        }
        $chunk = $this->books->chunkCoveringPage($book['version_id'], $pageNumber);
        $figures = $this->images->forPage($book['version_id'], $pageNumber);

        return [
            'page_number' => $pageNumber,
            'page_count' => $pageCount,
            'blocks' => $this->blocks((string) $page['normalized_text'], $figures, $slug),
            'chunk' => null === $chunk ? null : [
                'id' => $chunk['id'],
                'position' => (int) $chunk['position'],
                'start_page' => (int) $chunk['start_page'],
                'end_page' => (int) $chunk['end_page'],
                'content' => (string) $chunk['content'],
            ],
        ];
    }

    public function figureImage(string $slug, string $id): array
    {
        $book = $this->requirePublished($slug);
        $image = $this->images->byId($id);
        if (null === $image || $image['version_id'] !== $book['version_id'] || !is_file((string) $image['storage_path'])) {
            throw new DomainException('Photo introuvable.', 404);
        }

        return ['path' => $image['storage_path'], 'mime' => $image['mime_type']];
    }

    private function blocks(string $text, array $figures, string $slug): array
    {
        $paragraphs = preg_split('/\n\s*\n/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $count = count($paragraphs);
        $byIndex = [];
        foreach ($figures as $figure) {
            $ratio = (float) $figure['y_ratio'];
            $insertAt = 0 === $count ? 0 : (int) max(0, min($count, (int) round($ratio * $count)));
            $byIndex[$insertAt][] = $figure;
        }
        $blocks = [];
        $pendingText = [];
        $flushText = static function () use (&$blocks, &$pendingText): void {
            if ([] === $pendingText) {
                return;
            }
            $blocks[] = ['type' => 'text', 'content' => implode("\n\n", $pendingText)];
            $pendingText = [];
        };
        for ($index = 0; $index <= $count; ++$index) {
            foreach ($byIndex[$index] ?? [] as $figure) {
                $flushText();
                $blocks[] = [
                    'type' => 'image',
                    'url' => '/api/books/'.$slug.'/figures/'.$figure['id'].'/image',
                    'width_px' => (int) $figure['width_px'],
                    'height_px' => (int) $figure['height_px'],
                ];
            }
            if ($index < $count) {
                $pendingText[] = $paragraphs[$index];
            }
        }
        $flushText();
        if ([] === $blocks && '' !== trim($text)) {
            $blocks[] = ['type' => 'text', 'content' => trim($text)];
        }

        return $blocks;
    }

    public function saveProgress(User $user, string $slug, string $chunkId): array
    {
        $book = $this->requirePublished($slug);
        $chunk = $this->books->publishedChunk($book['version_id'], $chunkId);
        if (null === $chunk) {
            throw new DomainException('Passage introuvable.', 404);
        }
        $this->reading->upsertProgress($user->id, $book['version_id'], $chunkId, (int) $chunk['start_page']);

        return $this->reading->progress($user->id, $book['version_id']) ?? [];
    }

    public function savePageProgress(User $user, string $slug, int $pageNumber): array
    {
        $book = $this->requirePublished($slug);
        $pageCount = (int) ($book['page_count'] ?? 0);
        if ($pageNumber < 1 || $pageNumber > $pageCount) {
            throw new DomainException('Page introuvable.', 404);
        }
        $chunk = $this->books->chunkCoveringPage($book['version_id'], $pageNumber);
        if (null === $chunk) {
            throw new DomainException('Passage introuvable.', 404);
        }
        $this->reading->upsertProgress($user->id, $book['version_id'], $chunk['id'], $pageNumber);

        return $this->reading->progress($user->id, $book['version_id']) ?? [];
    }

    public function dashboard(User $user): array
    {
        $stats = $this->reading->dashboard($user->id);
        $catalog = $this->books->publishedCatalog();

        return ['stats' => $stats, 'library' => $catalog];
    }

    public function addBookmark(User $user, string $slug, string $chunkId): array
    {
        $book = $this->requirePublished($slug);
        if (null === $this->books->publishedChunk($book['version_id'], $chunkId)) {
            throw new DomainException('Passage introuvable.', 404);
        }
        try {
            $id = Uuid::v7()->toRfc4122();
            $this->reading->addBookmark($id, $user->id, $book['version_id'], $chunkId);

            return ['id' => $id];
        } catch (UniqueConstraintViolationException) {
            throw new DomainException('Ce passage est déjà dans vos favoris.', 409);
        }
    }

    public function bookmarks(User $user): array
    {
        return ['items' => $this->reading->bookmarks($user->id)];
    }

    public function removeBookmark(User $user, string $id): void
    {
        if (1 !== $this->reading->deleteBookmark($id, $user->id)) {
            throw new DomainException('Favori introuvable.', 404);
        }
    }

    public function addNote(User $user, string $slug, string $chunkId, string $body): array
    {
        $body = trim($body);
        if (mb_strlen($body) < 2) {
            throw new DomainException('La note est trop courte.');
        }
        $book = $this->requirePublished($slug);
        if (null === $this->books->publishedChunk($book['version_id'], $chunkId)) {
            throw new DomainException('Passage introuvable.', 404);
        }
        $id = Uuid::v7()->toRfc4122();
        $this->reading->addNote($id, $user->id, $book['version_id'], $chunkId, $body);

        return ['id' => $id];
    }

    public function notes(User $user): array
    {
        return ['items' => $this->reading->notes($user->id)];
    }

    public function updateNote(User $user, string $id, string $body): void
    {
        $body = trim($body);
        if (mb_strlen($body) < 2) {
            throw new DomainException('La note est trop courte.');
        }
        if (1 !== $this->reading->updateNote($id, $user->id, $body)) {
            throw new DomainException('Note introuvable.', 404);
        }
    }

    public function removeNote(User $user, string $id): void
    {
        if (1 !== $this->reading->deleteNote($id, $user->id)) {
            throw new DomainException('Note introuvable.', 404);
        }
    }

    public function addHighlight(User $user, string $slug, string $chunkId, string $excerpt): array
    {
        $excerpt = trim($excerpt);
        if ('' === $excerpt) {
            throw new DomainException('Le surlignage est vide.');
        }
        $book = $this->requirePublished($slug);
        $chunk = $this->books->publishedChunk($book['version_id'], $chunkId);
        if (null === $chunk) {
            throw new DomainException('Passage introuvable.', 404);
        }
        if (!str_contains((string) $chunk['content'], $excerpt)) {
            throw new DomainException('Le surlignage doit appartenir au passage.');
        }
        $id = Uuid::v7()->toRfc4122();
        $this->reading->addHighlight($id, $user->id, $book['version_id'], $chunkId, $excerpt);

        return ['id' => $id];
    }

    public function highlights(User $user): array
    {
        return ['items' => $this->reading->highlights($user->id)];
    }

    public function removeHighlight(User $user, string $id): void
    {
        if (1 !== $this->reading->deleteHighlight($id, $user->id)) {
            throw new DomainException('Surlignage introuvable.', 404);
        }
    }

    private function requirePublished(string $slug): array
    {
        $book = $this->books->publishedBySlug($slug);
        if (null === $book) {
            throw new DomainException('Aucune version publiée de ce livre n’est disponible.', 404);
        }

        return $book;
    }
}
