<?php

namespace App\Book;

use Doctrine\DBAL\Connection;

final readonly class EmbeddingRepository
{
    public function __construct(private Connection $connection) {}

    public function pendingChunks(string $versionId, int $limit): array
    {
        return $this->connection->fetchAllAssociative('SELECT id, content FROM book_chunks WHERE version_id = :version_id AND embedding IS NULL ORDER BY position LIMIT :limit', ['version_id' => $versionId, 'limit' => $limit], ['limit' => \Doctrine\DBAL\ParameterType::INTEGER]);
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
