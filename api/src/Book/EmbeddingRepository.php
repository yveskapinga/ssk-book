<?php

namespace App\Book;

use Doctrine\DBAL\Connection;

final readonly class EmbeddingRepository
{
    public function __construct(private Connection $connection) {}

    public function pendingChunks(string $versionId, int $limit): array
    {
        return $this->connection->fetchAllAssociative(
            <<<'SQL'
SELECT c.id, c.content, c.start_page, c.end_page, b.title AS book_title
FROM book_chunks c
JOIN book_versions v ON v.id = c.version_id
JOIN books b ON b.id = v.book_id
WHERE c.version_id = :version_id AND c.embedding IS NULL
ORDER BY c.position
LIMIT :limit
SQL,
            ['version_id' => $versionId, 'limit' => $limit],
            ['limit' => \Doctrine\DBAL\ParameterType::INTEGER],
        );
    }

    public function save(string $chunkId, array $vector, string $model): void
    {
        $literal = '['.implode(',', array_map(static fn ($value): string => sprintf('%.10F', (float) $value), $vector)).']';
        $this->connection->executeStatement('UPDATE book_chunks SET embedding = CAST(:embedding AS vector), embedding_model = :model, embedded_at = CURRENT_TIMESTAMP WHERE id = :id AND embedding IS NULL', ['id' => $chunkId, 'embedding' => $literal, 'model' => $model]);
    }

    public function counts(string $versionId): array
    {
        return $this->connection->fetchAssociative('SELECT COUNT(*) AS total, COUNT(embedding) AS embedded FROM book_chunks WHERE version_id = :id', ['id' => $versionId]) ?: ['total' => 0, 'embedded' => 0];
    }
}
