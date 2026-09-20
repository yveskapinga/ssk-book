<?php

namespace App\Book;

use Doctrine\DBAL\Connection;

final readonly class SearchRepository
{
    public function __construct(private Connection $connection) {}

    /**
     * @param list<float> $vector
     * @return list<array<string, mixed>>
     */
    public function hybrid(string $bookSlug, string $question, array $vector, int $limit = 8): array
    {
        $literal = '['.implode(',', array_map(static fn ($value): string => sprintf('%.10F', (float) $value), $vector)).']';
        $tokensJson = json_encode(SearchQuestionTerms::tokens($question), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $tsquery = SearchQuestionTerms::tsquery($question) ?? '';

        return $this->connection->fetchAllAssociative(<<<'SQL'
WITH current_book AS (
    SELECT b.current_version_id AS version_id FROM books b WHERE b.slug = :slug AND b.status = 'ACTIVE'
), name_tokens AS (
    SELECT lower(token) AS token
    FROM json_array_elements_text(CAST(:tokens_json AS json)) AS token
    WHERE length(token) >= 4
), vector_hits AS (
    SELECT c.id, ROW_NUMBER() OVER (ORDER BY c.embedding <=> CAST(:embedding AS vector)) AS rank
    FROM book_chunks c JOIN current_book b ON b.version_id = c.version_id
    WHERE c.embedding IS NOT NULL ORDER BY c.embedding <=> CAST(:embedding AS vector) LIMIT 20
), text_hits AS (
    SELECT c.id, ROW_NUMBER() OVER (ORDER BY ts_rank_cd(to_tsvector('french', c.content), query.q) DESC) AS rank
    FROM book_chunks c
    JOIN current_book b ON b.version_id = c.version_id
    CROSS JOIN LATERAL (SELECT CASE WHEN :tsquery = '' THEN NULL ELSE to_tsquery('french', :tsquery) END AS q) query
    WHERE query.q IS NOT NULL AND to_tsvector('french', c.content) @@ query.q
    ORDER BY ts_rank_cd(to_tsvector('french', c.content), query.q) DESC LIMIT 20
), name_ranked AS (
    SELECT c.id, ROW_NUMBER() OVER (ORDER BY MAX(word_similarity(n.token, c.content)) DESC) AS rank
    FROM book_chunks c
    JOIN current_book b ON b.version_id = c.version_id
    JOIN name_tokens n ON TRUE
    WHERE word_similarity(n.token, c.content) >= 0.32
       OR strpos(lower(c.content), n.token) > 0
    GROUP BY c.id
), name_hits AS (
    SELECT id, rank FROM name_ranked WHERE rank <= 20
), fused AS (
    SELECT id, SUM(score) AS score FROM (
        SELECT id, 1.0 / (60 + rank) AS score FROM vector_hits
        UNION ALL SELECT id, 1.0 / (50 + rank) AS score FROM text_hits
        UNION ALL SELECT id, 1.0 / (40 + rank) AS score FROM name_hits
    ) ranked GROUP BY id
)
SELECT c.id, c.start_page, c.end_page, c.content, f.score
FROM fused f JOIN book_chunks c ON c.id = f.id
ORDER BY f.score DESC LIMIT :limit
SQL, [
            'slug' => $bookSlug,
            'embedding' => $literal,
            'tsquery' => $tsquery,
            'tokens_json' => $tokensJson,
            'limit' => $limit,
        ], ['limit' => \Doctrine\DBAL\ParameterType::INTEGER]);
    }
}
