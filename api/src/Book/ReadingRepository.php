<?php

namespace App\Book;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final readonly class ReadingRepository
{
    public function __construct(private Connection $connection)
    {
    }

    public function upsertProgress(string $userId, string $versionId, string $chunkId, int $pageNumber): void
    {
        $this->connection->executeStatement(<<<'SQL'
INSERT INTO reading_progress (user_id, version_id, chunk_id, page_number, updated_at)
VALUES (:user_id, :version_id, :chunk_id, :page_number, CURRENT_TIMESTAMP)
ON CONFLICT (user_id, version_id) DO UPDATE
SET chunk_id = EXCLUDED.chunk_id, page_number = EXCLUDED.page_number, updated_at = CURRENT_TIMESTAMP
SQL, [
            'user_id' => $userId,
            'version_id' => $versionId,
            'chunk_id' => $chunkId,
            'page_number' => $pageNumber,
        ], ['page_number' => ParameterType::INTEGER]);
    }

    public function progress(string $userId, string $versionId): ?array
    {
        $row = $this->connection->fetchAssociative(<<<'SQL'
SELECT p.chunk_id, p.page_number, p.updated_at, c.position, c.start_page, c.end_page
FROM reading_progress p
JOIN book_chunks c ON c.id = p.chunk_id
WHERE p.user_id = :user_id AND p.version_id = :version_id
SQL, ['user_id' => $userId, 'version_id' => $versionId]);

        return false === $row ? null : $row;
    }

    public function addBookmark(string $id, string $userId, string $versionId, string $chunkId): void
    {
        $this->connection->insert('bookmarks', [
            'id' => $id, 'user_id' => $userId, 'version_id' => $versionId, 'chunk_id' => $chunkId,
        ]);
    }

    public function bookmarks(string $userId): array
    {
        return $this->connection->fetchAllAssociative(<<<'SQL'
SELECT b.id, b.chunk_id, b.created_at, c.start_page, c.end_page, c.position, left(c.content, 280) AS excerpt, bk.slug, bk.title
FROM bookmarks b
JOIN book_chunks c ON c.id = b.chunk_id
JOIN book_versions v ON v.id = b.version_id
JOIN books bk ON bk.id = v.book_id
WHERE b.user_id = :user_id
ORDER BY b.created_at DESC
SQL, ['user_id' => $userId]);
    }

    public function deleteBookmark(string $id, string $userId): int
    {
        return $this->connection->executeStatement('DELETE FROM bookmarks WHERE id = :id AND user_id = :user_id', ['id' => $id, 'user_id' => $userId]);
    }

    public function addNote(string $id, string $userId, string $versionId, string $chunkId, string $body): void
    {
        $this->connection->insert('reader_notes', [
            'id' => $id, 'user_id' => $userId, 'version_id' => $versionId, 'chunk_id' => $chunkId, 'body' => $body,
        ]);
    }

    public function notes(string $userId): array
    {
        return $this->connection->fetchAllAssociative(<<<'SQL'
SELECT n.id, n.chunk_id, n.body, n.created_at, n.updated_at, c.start_page, c.end_page, bk.slug, bk.title
FROM reader_notes n
JOIN book_chunks c ON c.id = n.chunk_id
JOIN book_versions v ON v.id = n.version_id
JOIN books bk ON bk.id = v.book_id
WHERE n.user_id = :user_id
ORDER BY n.updated_at DESC
SQL, ['user_id' => $userId]);
    }

    public function updateNote(string $id, string $userId, string $body): int
    {
        return $this->connection->executeStatement(
            'UPDATE reader_notes SET body = :body, updated_at = CURRENT_TIMESTAMP WHERE id = :id AND user_id = :user_id',
            ['id' => $id, 'user_id' => $userId, 'body' => $body],
        );
    }

    public function deleteNote(string $id, string $userId): int
    {
        return $this->connection->executeStatement('DELETE FROM reader_notes WHERE id = :id AND user_id = :user_id', ['id' => $id, 'user_id' => $userId]);
    }

    public function addHighlight(string $id, string $userId, string $versionId, string $chunkId, string $excerpt): void
    {
        $this->connection->insert('highlights', [
            'id' => $id, 'user_id' => $userId, 'version_id' => $versionId, 'chunk_id' => $chunkId, 'excerpt' => $excerpt,
        ]);
    }

    public function highlights(string $userId): array
    {
        return $this->connection->fetchAllAssociative(<<<'SQL'
SELECT h.id, h.chunk_id, h.excerpt, h.created_at, c.start_page, c.end_page, bk.slug, bk.title
FROM highlights h
JOIN book_chunks c ON c.id = h.chunk_id
JOIN book_versions v ON v.id = h.version_id
JOIN books bk ON bk.id = v.book_id
WHERE h.user_id = :user_id
ORDER BY h.created_at DESC
SQL, ['user_id' => $userId]);
    }

    public function deleteHighlight(string $id, string $userId): int
    {
        return $this->connection->executeStatement('DELETE FROM highlights WHERE id = :id AND user_id = :user_id', ['id' => $id, 'user_id' => $userId]);
    }

    public function dashboard(string $userId): array
    {
        return $this->connection->fetchAssociative(<<<'SQL'
SELECT
    COALESCE((
        SELECT ROUND(100.0 * (c.position + 1) / NULLIF((SELECT COUNT(*) FROM book_chunks x WHERE x.version_id = p.version_id), 0), 0)
        FROM reading_progress p
        JOIN book_chunks c ON c.id = p.chunk_id
        WHERE p.user_id = :user_id
        ORDER BY p.updated_at DESC LIMIT 1
    ), 0) AS reading_percent,
    (SELECT COUNT(*) FROM bookmarks WHERE user_id = :user_id) AS bookmark_count,
    (SELECT COUNT(*) FROM reader_notes WHERE user_id = :user_id) AS note_count,
    (SELECT COUNT(*) FROM quiz_attempts WHERE user_id = :user_id AND status = 'COMPLETED') AS quiz_count,
    (SELECT COUNT(*) FROM conversation_messages m JOIN conversations c ON c.id = m.conversation_id WHERE c.user_id = :user_id AND m.role = 'USER') AS question_count
SQL, ['user_id' => $userId]) ?: [
            'reading_percent' => 0, 'bookmark_count' => 0, 'note_count' => 0, 'quiz_count' => 0, 'question_count' => 0,
        ];
    }
}
