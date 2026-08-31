<?php

namespace App\Book;

use Doctrine\DBAL\Connection;

final readonly class BookRepository
{
    public function __construct(private Connection $connection)
    {
    }

    public function findBySlug(string $slug): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT id, slug, title, description, status FROM books WHERE slug = :slug',
            ['slug' => $slug],
        );

        return false === $row ? null : $row;
    }

    public function create(string $id, string $slug, string $title, ?string $description): void
    {
        $this->connection->insert('books', [
            'id' => $id,
            'slug' => $slug,
            'title' => $title,
            'description' => $description,
        ]);
    }

    public function lockAndNextVersionNumber(string $bookId): int
    {
        $this->connection->executeStatement('SELECT pg_advisory_xact_lock(hashtext(:book_id))', ['book_id' => $bookId]);

        return 1 + (int) $this->connection->fetchOne(
            'SELECT COALESCE(MAX(version_number), 0) FROM book_versions WHERE book_id = :book_id',
            ['book_id' => $bookId],
        );
    }

    public function createVersion(array $data): void
    {
        $this->connection->insert('book_versions', $data);
    }

    public function updateVersionStatus(string $versionId, string $status, ?int $pageCount = null): void
    {
        $sql = 'UPDATE book_versions SET status = :status, updated_at = CURRENT_TIMESTAMP';
        $parameters = ['id' => $versionId, 'status' => $status];
        if (null !== $pageCount) {
            $sql .= ', page_count = :page_count';
            $parameters['page_count'] = $pageCount;
        }
        $this->connection->executeStatement($sql.' WHERE id = :id', $parameters);
    }

    public function findVersion(string $versionId): ?array
    {
        $row = $this->connection->fetchAssociative(
            <<<'SQL'
SELECT v.id, v.book_id, v.version_number, v.label, v.status, v.source_filename,
       v.source_path, v.source_sha256, v.page_count, v.created_at,
       b.slug AS book_slug, b.title AS book_title
FROM book_versions v
JOIN books b ON b.id = v.book_id
WHERE v.id = :id
SQL,
            ['id' => $versionId],
        );

        return false === $row ? null : $row;
    }

    public function replacePages(string $versionId, array $pages): void
    {
        $this->connection->executeStatement('DELETE FROM book_pages WHERE version_id = :version_id', ['version_id' => $versionId]);
        foreach ($pages as $page) {
            $this->connection->insert('book_pages', $page);
        }
    }

    public function replaceChunks(string $versionId, array $chunks): void
    {
        $this->connection->executeStatement('DELETE FROM book_chunks WHERE version_id = :version_id', ['version_id' => $versionId]);
        foreach ($chunks as $chunk) {
            $this->connection->insert('book_chunks', $chunk);
        }
    }
}
