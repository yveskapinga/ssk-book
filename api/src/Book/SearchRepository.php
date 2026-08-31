<?php

namespace App\Book;

use Doctrine\DBAL\Connection;

final readonly class SearchRepository
{
    public function __construct(private Connection $connection) {}

    public function hybrid(string $bookSlug, string $question, array $vector, int $limit = 8): array
    {
        $literal = '['.implode(',', array_map(static fn ($value): string => sprintf('%.10F', (float) $value), $vector)).']';
        return $this->connection->fetchAllAssociative(<<<'SQL'
WITH current_book AS (
    SELECT b.current_version_id AS version_id FROM books b WHERE b.slug = :slug AND b.status = 'ACTIVE'
), vector_hits AS (
    SELECT c.id, ROW_NUMBER() OVER (ORDER BY c.embedding <=> CAST(:embedding AS vector)) AS rank
    FROM book_chunks c JOIN current_book b ON b.version_id = c.version_id
    WHERE c.embedding IS NOT NULL ORDER BY c.embedding <=> CAST(:embedding AS vector) LIMIT 20
), text_hits AS (
    SELECT c.id, ROW_NUMBER() OVER (ORDER BY ts_rank_cd(to_tsvector('french', c.content), websearch_to_tsquery('french', :question)) DESC) AS rank
    FROM book_chunks c JOIN current_book b ON b.version_id = c.version_id
    WHERE to_tsvector('french', c.content) @@ websearch_to_tsquery('french', :question)
    ORDER BY ts_rank_cd(to_tsvector('french', c.content), websearch_to_tsquery('french', :question)) DESC LIMIT 20
), fused AS (
    SELECT id, SUM(score) AS score FROM (
        SELECT id, 1.0 / (60 + rank) AS score FROM vector_hits
        UNION ALL SELECT id, 1.0 / (60 + rank) AS score FROM text_hits
    ) ranked GROUP BY id
)
SELECT c.id, c.start_page, c.end_page, c.content, f.score
FROM fused f JOIN book_chunks c ON c.id = f.id
ORDER BY f.score DESC LIMIT :limit
SQL, ['slug' => $bookSlug, 'embedding' => $literal, 'question' => $question, 'limit' => $limit], ['limit' => \Doctrine\DBAL\ParameterType::INTEGER]);
    }
}
