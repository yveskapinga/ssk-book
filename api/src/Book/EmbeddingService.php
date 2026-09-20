<?php

namespace App\Book;

use App\Ai\GeminiClient;
use App\Audit\AuditRepository;
use App\Identity\User;
use App\Shared\DomainException;
use Doctrine\DBAL\Connection;

final readonly class EmbeddingService
{
    public function __construct(private Connection $connection, private BookRepository $books, private EmbeddingRepository $embeddings, private GeminiClient $gemini, private AuditRepository $audit) {}

    public function index(User $actor, string $versionId): array
    {
        $version = $this->books->findVersion($versionId);
        if (null === $version) throw new DomainException('Version introuvable.', 404);
        if (!in_array($version['status'], ['REVIEW_REQUIRED', 'EMBEDDING'], true)) throw new DomainException('Cette version ne peut pas être indexée.', 409);
        $this->books->updateVersionStatus($versionId, 'EMBEDDING');
        try {
            // Partial runs avoid HTTP timeouts under Gemini 429 backoff; caller resumes until done.
            $processed = 0;
            $maxPerRun = 40;
            while ($processed < $maxPerRun && [] !== ($chunks = $this->embeddings->pendingChunks($versionId, 8))) {
                $vectors = $this->gemini->embed(array_column($chunks, 'content'));
                $this->connection->transactional(function () use ($chunks, $vectors): void {
                    foreach ($chunks as $index => $chunk) {
                        $this->embeddings->save($chunk['id'], $vectors[$index], $this->gemini->embeddingModel());
                    }
                });
                $processed += count($chunks);
                usleep(400_000);
            }
            $counts = $this->embeddings->counts($versionId);
            $done = (int) $counts['embedded'] === (int) $counts['total'] && (int) $counts['total'] > 0;
            $this->connection->transactional(function () use ($actor, $versionId, $counts, $done): void {
                $this->books->updateVersionStatus($versionId, 'REVIEW_REQUIRED');
                if ($done) {
                    $this->audit->append($actor->id, 'BOOK_VERSION_EMBEDDED', 'BOOK_VERSION', $versionId, $counts);
                }
            });
            $counts['done'] = $done;
            $counts['processed'] = $processed;

            return $counts;
        } catch (\Throwable $exception) {
            $this->books->updateVersionStatus($versionId, 'REVIEW_REQUIRED');
            throw $exception;
        }
    }
}
