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
        private ReadingProgressPolicy $policy,
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
            'passageCount' => $this->books->passageCount($versionId),
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

    public function currentReading(User $user, string $slug, ?string $passageId = null, int $pageNumber = 0): array
    {
        $book = $this->requirePublished($slug);
        $versionId = $book['version_id'];
        $total = $this->books->passageCount($versionId);
        if (0 === $total) {
            throw new DomainException('La lecture par passage n’est pas encore disponible pour ce livre.', 404);
        }

        $frontierRow = $this->reading->frontier($user->id, $versionId);
        $allRead = null === $frontierRow;
        $frontier = $frontierRow ?? $this->books->lastPassage($versionId);
        if (null === $frontier) {
            throw new DomainException('Aucun passage n’est disponible.', 404);
        }
        $maxPosition = (int) $frontier['position'];

        $requested = null;
        if (is_string($passageId) && '' !== $passageId) {
            $requested = $this->books->publishedPassage($versionId, $passageId);
            if (null === $requested) {
                throw new DomainException('Passage introuvable.', 404);
            }
        } elseif ($pageNumber > 0) {
            $requested = $this->books->firstPassageAtPage($versionId, $pageNumber);
            if (null === $requested) {
                throw new DomainException('Aucun passage sur cette page.', 404);
            }
        }

        $target = $requested ?? $frontier;
        if ((int) $target['position'] > $maxPosition) {
            throw new DomainException('Ce passage n’est pas encore déverrouillé.', 403);
        }

        $passage = $this->books->publishedPassage($versionId, (string) $target['id']);
        if (null === $passage) {
            throw new DomainException('Passage introuvable.', 404);
        }

        return $this->presentReading($user, $slug, $versionId, $passage, $frontier, $total, $allRead);
    }

    public function observeReading(User $user, string $slug, string $passageId, int $displayedMs, bool $advance = false): array
    {
        $book = $this->requirePublished($slug);
        $versionId = $book['version_id'];
        $passage = $this->books->publishedPassage($versionId, $passageId);
        if (null === $passage) {
            throw new DomainException('Passage introuvable.', 404);
        }

        $frontier = $this->reading->frontier($user->id, $versionId);
        $allRead = null === $frontier;
        if (null === $frontier) {
            $frontier = $this->books->lastPassage($versionId);
        }
        if (null === $frontier) {
            throw new DomainException('Aucun passage n’est disponible.', 404);
        }
        if ((int) $passage['position'] > (int) $frontier['position'] && !$allRead) {
            throw new DomainException('Ce passage n’est pas encore déverrouillé.', 403);
        }

        $existing = $this->reading->passageProgress($user->id, $passageId);
        $alreadyRead = 'READ' === ($existing['status'] ?? null);
        $sequential = !$allRead && (int) $passage['position'] === (int) $frontier['position'];
        $displayedMs = max(0, min(120_000, $displayedMs));
        $totalMs = (int) ($existing['displayed_ms'] ?? 0) + $displayedMs;
        $decision = $this->policy->decide($alreadyRead, $sequential, $totalMs, mb_strlen((string) $passage['body']), $advance);
        $this->reading->upsertPassageProgress($user->id, $passageId, $decision['status'], $totalMs, $decision['confidence']);

        $chunkId = (string) ($passage['chunk_id'] ?? '');
        if ('' === $chunkId) {
            $covering = $this->books->chunkCoveringPage($versionId, (int) $passage['page_number']);
            $chunkId = (string) ($covering['id'] ?? '');
        }
        if ('' !== $chunkId) {
            $this->reading->upsertProgress($user->id, $versionId, $chunkId, (int) $passage['page_number'], $passageId);
        }

        $presented = $this->currentReading($user, $slug, $passageId);
        if ($advance && is_string($presented['nextId'] ?? null) && '' !== $presented['nextId']) {
            return $this->currentReading($user, $slug, (string) $presented['nextId']);
        }

        return $presented;
    }

    /**
     * @param array<string, mixed> $passage
     * @param array<string, mixed> $frontier
     */
    private function presentReading(User $user, string $slug, string $versionId, array $passage, array $frontier, int $total, bool $allRead): array
    {
        $progress = $this->reading->passageProgress($user->id, (string) $passage['id']);
        $status = (string) ($progress['status'] ?? 'IN_PROGRESS');
        $position = (int) $passage['position'];
        $frontierPosition = (int) $frontier['position'];
        $previous = $this->books->passageNeighbor($versionId, $position, -1);
        $next = $this->books->passageNeighbor($versionId, $position, 1);
        $canAdvance = null !== $next;
        $bodyLength = mb_strlen((string) $passage['body']);
        $figure = null;
        if ('IMAGE' === $passage['kind'] && null !== ($passage['figure_id'] ?? null)) {
            $figure = [
                'id' => $passage['figure_id'],
                'url' => '/api/books/'.$slug.'/figures/'.$passage['figure_id'].'/image',
                'width_px' => (int) ($passage['width_px'] ?? 0),
                'height_px' => (int) ($passage['height_px'] ?? 0),
            ];
        }
        $chunk = null;
        if (null !== ($passage['chunk_id'] ?? null)) {
            $row = $this->books->publishedChunk($versionId, (string) $passage['chunk_id']);
            $chunk = null === $row ? null : [
                'id' => $row['id'],
                'position' => (int) $row['position'],
                'start_page' => (int) $row['start_page'],
                'end_page' => (int) $row['end_page'],
                'content' => (string) $row['content'],
            ];
        }

        return [
            'passage' => [
                'id' => $passage['id'],
                'position' => $position,
                'page_number' => (int) $passage['page_number'],
                'kind' => $passage['kind'],
                'body' => (string) $passage['body'],
                'chapter_title' => $passage['chapter_title'] ?? null,
                'figure' => $figure,
                'chunk' => $chunk,
            ],
            'status' => $status,
            'confidence' => isset($progress['confidence']) ? (float) $progress['confidence'] : 0.0,
            'estimatedSeconds' => $this->policy->estimatedSeconds($bodyLength),
            'total' => $total,
            'frontier' => [
                'id' => $frontier['id'],
                'position' => $frontierPosition,
                'page_number' => (int) $frontier['page_number'],
            ],
            'previousId' => $previous['id'] ?? null,
            'nextId' => $next['id'] ?? null,
            'canAdvance' => $canAdvance,
            'complete' => $allRead,
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
