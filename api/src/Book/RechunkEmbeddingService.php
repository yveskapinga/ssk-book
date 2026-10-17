<?php

namespace App\Book;

use App\Identity\User;
use App\Shared\DomainException;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

/**
 * Rebuild search chunks from stored pages, then re-embed.
 * Temporarily lifts PUBLISHED immutability; restores PUBLISHED when done.
 */
final readonly class RechunkEmbeddingService
{
    public function __construct(
        private Connection $connection,
        private BookRepository $books,
        private BookChunker $chunker,
        private EmbeddingService $embeddings,
        private PassageIndexer $passages,
    ) {
    }

    /**
     * @return array{versionId:string,chunks:int,embedded:int,total:int,done:bool,previousStatus:string}
     */
    public function rechunk(User $actor, string $versionId, bool $embedUntilDone = true): array
    {
        $version = $this->books->findVersion($versionId);
        if (null === $version) {
            throw new DomainException('Version introuvable.', 404);
        }

        $pages = $this->books->pagesForVersion($versionId);
        if ($pages === []) {
            throw new DomainException('Aucune page stockée pour cette version — impossible de rechunker sans PDF.', 409);
        }

        $previousStatus = (string) $version['status'];
        $this->books->updateVersionStatus($versionId, 'REVIEW_REQUIRED');

        $chunks = $this->chunker->chunk($pages);
        $chunkRows = array_map(static fn (array $chunk): array => [
            'id' => Uuid::v7()->toRfc4122(),
            'version_id' => $versionId,
            'start_page' => $chunk['startPage'],
            'end_page' => $chunk['endPage'],
            'position' => $chunk['position'],
            'content' => $chunk['content'],
            'content_sha256' => hash('sha256', $chunk['content']),
            'token_estimate' => $chunk['tokenEstimate'],
        ], $chunks);

        $this->connection->transactional(function () use ($versionId, $chunkRows): void {
            $this->connection->executeStatement(
                'DELETE FROM message_sources WHERE chunk_id IN (SELECT id FROM book_chunks WHERE version_id = :version_id)',
                ['version_id' => $versionId],
            );
            $this->books->replaceChunks($versionId, $chunkRows);
        });

        $this->passages->rebuild($versionId, true);

        $counts = ['embedded' => 0, 'total' => count($chunkRows), 'done' => false, 'processed' => 0];
        if ($embedUntilDone) {
            $guard = 0;
            do {
                $counts = $this->embeddings->index($actor, $versionId);
                ++$guard;
            } while (!(bool) ($counts['done'] ?? false) && $guard < 500);
        }

        if ($previousStatus === 'PUBLISHED' && (bool) ($counts['done'] ?? false)) {
            $this->books->updateVersionStatus($versionId, 'PUBLISHED');
        } elseif ($previousStatus === 'PUBLISHED' && !(bool) ($counts['done'] ?? false)) {
            // Keep REVIEW_REQUIRED / EMBEDDING path incomplete — caller must resume embed.
            $this->books->updateVersionStatus($versionId, 'REVIEW_REQUIRED');
        } else {
            $this->books->updateVersionStatus($versionId, $previousStatus === 'EMBEDDING' ? 'REVIEW_REQUIRED' : $previousStatus);
        }

        return [
            'versionId' => $versionId,
            'chunks' => count($chunkRows),
            'embedded' => (int) ($counts['embedded'] ?? 0),
            'total' => (int) ($counts['total'] ?? count($chunkRows)),
            'done' => (bool) ($counts['done'] ?? false),
            'previousStatus' => $previousStatus,
        ];
    }
}
