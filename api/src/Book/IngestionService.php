<?php

namespace App\Book;

use App\Audit\AuditRepository;
use App\Identity\User;
use App\Shared\DomainException;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

final readonly class IngestionService
{
    public function __construct(
        private Connection $connection,
        private BookRepository $books,
        private IngestionJobRepository $jobs,
        private PdfTextExtractor $extractor,
        private BookChunker $chunker,
        private BookStructureDetector $structure,
        private PageImageService $pageImages,
        private PassageIndexer $passages,
        private AuditRepository $audit,
    ) {
    }

    public function run(User $actor, string $jobId): array
    {
        $job = $this->jobs->find($jobId);
        if (null === $job) {
            throw new DomainException('Tâche d’ingestion introuvable.', 404);
        }
        if ('FAILED' === $job['status']) {
            if (1 !== $this->jobs->reopenFailed($jobId)) {
                throw new DomainException('Cette tâche ne peut pas être relancée.', 409);
            }
            $job = $this->jobs->find($jobId) ?? $job;
        }
        if ('PENDING' !== $job['status']) {
            throw new DomainException('Cette tâche ne peut plus être démarrée.', 409);
        }
        $version = $this->books->findVersion($job['version_id']);
        if (null === $version) {
            throw new DomainException('Version du livre introuvable.', 404);
        }

        $this->jobs->start($jobId, 'EXTRACTING');
        $this->books->updateVersionStatus($version['id'], 'EXTRACTING');

        try {
            $pages = $this->extractor->extract($version['source_path']);
            $this->jobs->progress($jobId, 'STRUCTURING', 45, ['pages' => count($pages)]);
            $this->books->updateVersionStatus($version['id'], 'STRUCTURING');
            $chunks = $this->chunker->chunk($pages);
            $nodes = $this->structure->detect($pages);

            $this->connection->transactional(function () use ($actor, $jobId, $version, $pages, $chunks, $nodes): void {
                $pageRows = array_map(static fn (array $page): array => [
                    'id' => Uuid::v7()->toRfc4122(),
                    'version_id' => $version['id'],
                    'page_number' => $page['pageNumber'],
                    'raw_text' => $page['rawText'],
                    'normalized_text' => $page['normalizedText'],
                    'text_sha256' => hash('sha256', $page['normalizedText']),
                ], $pages);
                $chunkRows = array_map(static fn (array $chunk): array => [
                    'id' => Uuid::v7()->toRfc4122(),
                    'version_id' => $version['id'],
                    'start_page' => $chunk['startPage'],
                    'end_page' => $chunk['endPage'],
                    'position' => $chunk['position'],
                    'content' => $chunk['content'],
                    'content_sha256' => hash('sha256', $chunk['content']),
                    'token_estimate' => $chunk['tokenEstimate'],
                ], $chunks);
                $this->books->replacePages($version['id'], $pageRows);
                $this->books->replaceChunks($version['id'], $chunkRows);
                $nodeRows = array_map(static function (array $node): array {
                    $node['id'] = Uuid::v7()->toRfc4122();

                    return $node;
                }, $nodes);
                $this->books->replaceNodes($version['id'], $nodeRows);
                $metrics = ['pages' => count($pages), 'chunks' => count($chunks), 'nodes' => count($nodeRows)];
                $this->books->updateVersionStatus($version['id'], 'REVIEW_REQUIRED', count($pages));
                $this->jobs->complete($jobId, $metrics);
                $this->audit->append($actor->id, 'BOOK_VERSION_STRUCTURED', 'BOOK_VERSION', $version['id'], $metrics);
            });

            try {
                $rendered = $this->pageImages->render($actor, $version['id']);
            } catch (\Throwable $exception) {
                error_log(sprintf('[ssk-book] page render after ingestion failed: %s', $exception->getMessage()));
                $rendered = [];
            }
            $indexed = $this->passages->rebuild($version['id']);
            $job = $this->jobs->find($jobId) ?? throw new \RuntimeException('Ingestion result is unavailable.');
            $job['metrics'] = array_merge(
                is_array($job['metrics'] ?? null) ? $job['metrics'] : [],
                $rendered,
                $indexed,
            );

            return $job;
        } catch (\Throwable $exception) {
            $this->connection->transactional(function () use ($jobId, $version, $exception): void {
                $this->books->updateVersionStatus($version['id'], 'FAILED');
                $this->jobs->fail($jobId, $exception->getMessage());
            });
            throw $exception;
        }
    }

    public function status(string $jobId): array
    {
        return $this->jobs->find($jobId) ?? throw new DomainException('Tâche d’ingestion introuvable.', 404);
    }
}
