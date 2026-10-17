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
            $maxPerRun = 24;
            while ($processed < $maxPerRun && [] !== ($chunks = $this->embeddings->pendingChunks($versionId, 4))) {
                $texts = array_map(static fn (array $chunk): string => self::embedText($chunk), $chunks);
                $vectors = $this->gemini->embed($texts);
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

    /**
     * @param array{content:string,start_page?:int|string,end_page?:int|string,book_title?:string} $chunk
     */
    public static function embedText(array $chunk): string
    {
        $title = trim((string) ($chunk['book_title'] ?? ''));
        $start = (int) ($chunk['start_page'] ?? 0);
        $end = (int) ($chunk['end_page'] ?? $start);
        $content = (string) $chunk['content'];
        $header = [];
        if ($title !== '') {
            $header[] = 'Livre: '.$title;
        }
        if ($start > 0) {
            $header[] = $start === $end ? 'Pages: '.$start : 'Pages: '.$start.'-'.$end;
        }
        if ($header === []) {
            return $content;
        }

        return implode("\n", $header)."\n".$content;
    }
}
