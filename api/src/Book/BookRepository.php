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
            'SELECT id, slug, title, description, status, current_version_id FROM books WHERE slug = :slug',
            ['slug' => $slug],
        );

        return false === $row ? null : $row;
    }

    public function latestVersionId(string $bookId): ?string
    {
        $id = $this->connection->fetchOne(
            'SELECT id FROM book_versions WHERE book_id = :book_id ORDER BY version_number DESC LIMIT 1',
            ['book_id' => $bookId],
        );

        return false === $id || null === $id ? null : (string) $id;
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

    public function replaceNodes(string $versionId, array $nodes): void
    {
        $this->connection->executeStatement('DELETE FROM book_nodes WHERE version_id = :version_id', ['version_id' => $versionId]);
        $ids = [];
        foreach ($nodes as $index => $node) {
            $ids[$index] = $node['id'];
            $parentId = null;
            if (isset($node['parentIndex']) && null !== $node['parentIndex']) {
                $parentId = $ids[(int) $node['parentIndex']] ?? null;
            }
            $this->connection->insert('book_nodes', [
                'id' => $node['id'],
                'version_id' => $versionId,
                'parent_id' => $parentId,
                'node_type' => $node['nodeType'],
                'title' => $node['title'],
                'position' => $node['position'],
                'start_page' => $node['startPage'],
                'end_page' => $node['endPage'],
            ]);
        }
    }

    public function publishedCatalog(): array
    {
        return $this->connection->fetchAllAssociative(<<<'SQL'
SELECT b.id, b.slug, b.title, b.description, v.id AS version_id, v.version_number, v.page_count,
       (SELECT COUNT(*) FROM book_chunks c WHERE c.version_id = v.id) AS chunk_count
FROM books b
JOIN book_versions v ON v.id = b.current_version_id
WHERE b.status = 'ACTIVE' AND v.status = 'PUBLISHED'
ORDER BY b.title
SQL);
    }

    public function publishedBySlug(string $slug): ?array
    {
        $row = $this->connection->fetchAssociative(<<<'SQL'
SELECT b.id, b.slug, b.title, b.description, v.id AS version_id, v.version_number, v.label, v.page_count
FROM books b
JOIN book_versions v ON v.id = b.current_version_id
WHERE b.slug = :slug AND b.status = 'ACTIVE' AND v.status = 'PUBLISHED'
SQL, ['slug' => $slug]);

        return false === $row ? null : $row;
    }

    public function tableOfContents(string $versionId): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT id, parent_id, node_type, title, position, start_page, end_page FROM book_nodes WHERE version_id = :id ORDER BY position',
            ['id' => $versionId],
        );
    }

    public function publishedChunks(string $versionId, int $limit, int $offset): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT id, position, start_page, end_page, content FROM book_chunks WHERE version_id = :id ORDER BY position LIMIT :limit OFFSET :offset',
            ['id' => $versionId, 'limit' => $limit, 'offset' => $offset],
            ['limit' => \Doctrine\DBAL\ParameterType::INTEGER, 'offset' => \Doctrine\DBAL\ParameterType::INTEGER],
        );
    }

    public function publishedPage(string $versionId, int $pageNumber): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT page_number, normalized_text FROM book_pages WHERE version_id = :id AND page_number = :page',
            ['id' => $versionId, 'page' => $pageNumber],
            ['page' => \Doctrine\DBAL\ParameterType::INTEGER],
        );

        return false === $row ? null : $row;
    }

    public function chunkCoveringPage(string $versionId, int $page): ?array
    {
        $covering = $this->connection->fetchAssociative(
            'SELECT id, position, start_page, end_page, content FROM book_chunks WHERE version_id = :id AND start_page <= :page AND end_page >= :page ORDER BY position LIMIT 1',
            ['id' => $versionId, 'page' => $page],
            ['page' => \Doctrine\DBAL\ParameterType::INTEGER],
        );
        if (false !== $covering) {
            return $covering;
        }
        $previous = $this->connection->fetchAssociative(
            'SELECT id, position, start_page, end_page, content FROM book_chunks WHERE version_id = :id AND start_page <= :page ORDER BY start_page DESC, position DESC LIMIT 1',
            ['id' => $versionId, 'page' => $page],
            ['page' => \Doctrine\DBAL\ParameterType::INTEGER],
        );
        if (false !== $previous) {
            return $previous;
        }
        $first = $this->connection->fetchAssociative(
            'SELECT id, position, start_page, end_page, content FROM book_chunks WHERE version_id = :id ORDER BY position LIMIT 1',
            ['id' => $versionId],
        );

        return false === $first ? null : $first;
    }

    public function chunkPositionAtPage(string $versionId, int $page): ?int
    {
        $position = $this->connection->fetchOne(
            'SELECT position FROM book_chunks WHERE version_id = :id AND start_page <= :page ORDER BY start_page DESC, position DESC LIMIT 1',
            ['id' => $versionId, 'page' => $page],
            ['page' => \Doctrine\DBAL\ParameterType::INTEGER],
        );

        return false === $position || null === $position ? null : (int) $position;
    }

    public function chunkCount(string $versionId): int
    {
        return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM book_chunks WHERE version_id = :id', ['id' => $versionId]);
    }

    public function searchPublished(string $versionId, string $query, int $limit): array
    {
        return $this->connection->fetchAllAssociative(<<<'SQL'
SELECT id, position, start_page, end_page, content
FROM book_chunks
WHERE version_id = :id AND content ILIKE :query
ORDER BY position
LIMIT :limit
SQL, ['id' => $versionId, 'query' => '%'.$query.'%', 'limit' => $limit], ['limit' => \Doctrine\DBAL\ParameterType::INTEGER]);
    }

    public function publishedChunk(string $versionId, string $chunkId): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT id, position, start_page, end_page, content FROM book_chunks WHERE version_id = :version_id AND id = :id',
            ['version_id' => $versionId, 'id' => $chunkId],
        );

        return false === $row ? null : $row;
    }
}
