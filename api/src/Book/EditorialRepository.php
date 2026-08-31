<?php

namespace App\Book;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final readonly class EditorialRepository
{
    public function __construct(private Connection $connection)
    {
    }

    public function listVersions(int $limit, int $offset): array
    {
        return $this->connection->fetchAllAssociative(<<<'SQL'
SELECT v.id, v.book_id, b.title AS book_title, b.slug, v.version_number, v.label,
       v.status, v.page_count, v.created_at, v.published_at,
       (SELECT decision FROM book_version_reviews r WHERE r.version_id = v.id ORDER BY r.created_at DESC LIMIT 1) AS latest_decision,
       COUNT(c.id) AS chunk_count,
       COUNT(c.embedding) AS embedded_count
FROM book_versions v
JOIN books b ON b.id = v.book_id
LEFT JOIN book_chunks c ON c.version_id = v.id
GROUP BY v.id, b.id
ORDER BY v.created_at DESC
LIMIT :limit OFFSET :offset
SQL, ['limit' => $limit, 'offset' => $offset], ['limit' => ParameterType::INTEGER, 'offset' => ParameterType::INTEGER]);
    }

    public function chunks(string $versionId, int $limit, int $offset): array
    {
        return $this->connection->fetchAllAssociative(<<<'SQL'
SELECT id, position, start_page, end_page, content, token_estimate,
       embedding IS NOT NULL AS embedded
FROM book_chunks
WHERE version_id = :version_id
ORDER BY position
LIMIT :limit OFFSET :offset
SQL, ['version_id' => $versionId, 'limit' => $limit, 'offset' => $offset], ['limit' => ParameterType::INTEGER, 'offset' => ParameterType::INTEGER]);
    }

    public function addReview(string $id, string $versionId, string $reviewerId, string $decision, ?string $notes): void
    {
        $this->connection->insert('book_version_reviews', [
            'id' => $id, 'version_id' => $versionId, 'reviewer_id' => $reviewerId,
            'decision' => $decision, 'notes' => $notes,
        ]);
    }

    public function reviewSummary(string $versionId): array
    {
        return $this->connection->fetchAssociative(<<<'SQL'
SELECT v.id, v.book_id, v.status, v.page_count,
       COUNT(c.id) AS chunk_count,
       COUNT(c.embedding) AS embedded_count,
       (SELECT decision FROM book_version_reviews r WHERE r.version_id = v.id ORDER BY r.created_at DESC LIMIT 1) AS latest_decision
FROM book_versions v
LEFT JOIN book_chunks c ON c.version_id = v.id
WHERE v.id = :id
GROUP BY v.id
SQL, ['id' => $versionId]) ?: [];
    }

    public function reject(string $versionId): void
    {
        $this->connection->executeStatement("UPDATE book_versions SET status = 'FAILED', updated_at = CURRENT_TIMESTAMP WHERE id = :id AND status = 'REVIEW_REQUIRED'", ['id' => $versionId]);
    }

    public function publish(string $versionId, string $bookId): void
    {
        $this->connection->executeStatement("UPDATE book_versions SET status = 'PUBLISHED', published_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = :id AND status = 'REVIEW_REQUIRED'", ['id' => $versionId]);
        $this->connection->executeStatement('UPDATE books SET current_version_id = :version_id, updated_at = CURRENT_TIMESTAMP WHERE id = :book_id', ['version_id' => $versionId, 'book_id' => $bookId]);
    }
}
