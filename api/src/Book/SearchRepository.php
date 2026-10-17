<?php

namespace App\Book;

use Doctrine\DBAL\Connection;

final readonly class SearchRepository
{
    public function __construct(private Connection $connection) {}

    /**
     * Text + name + subject fusion only (no vector channel). Useful when Gemini quota blocks query embeds.
     *
     * @return list<array<string, mixed>>
     */
    public function hybridText(string $bookSlug, string $question, int $limit = 12): array
    {
        return $this->hybrid($bookSlug, $question, [], $limit, textOnly: true);
    }

    /**
     * @param list<float> $vector
     * @return list<array<string, mixed>>
     */
    public function hybrid(string $bookSlug, string $question, array $vector, int $limit = 12, bool $textOnly = false): array
    {
        $literal = $textOnly || $vector === []
            ? null
            : '['.implode(',', array_map(static fn ($value): string => sprintf('%.10F', (float) $value), $vector)).']';
        $tokensJson = json_encode(SearchQuestionTerms::tokens($question), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $tsqueryAnd = SearchQuestionTerms::tsquery($question) ?? '';
        $tsqueryOr = SearchQuestionTerms::tsqueryOr($question) ?? '';
        $subject = QuestionSubject::detect($question);

        if ($textOnly || null === $literal) {
            return $this->connection->fetchAllAssociative(<<<'SQL'
WITH current_book AS (
    SELECT b.current_version_id AS version_id FROM books b WHERE b.slug = :slug AND b.status = 'ACTIVE'
), name_tokens AS (
    SELECT lower(token) AS token
    FROM json_array_elements_text(CAST(:tokens_json AS json)) AS token
    WHERE length(token) >= 4
), text_query AS (
    SELECT CASE
        WHEN :tsquery_and <> '' AND EXISTS (
            SELECT 1
            FROM book_chunks c
            JOIN current_book b ON b.version_id = c.version_id
            WHERE to_tsvector('french', c.content) @@ to_tsquery('french', :tsquery_and)
        ) THEN to_tsquery('french', :tsquery_and)
        WHEN :tsquery_or <> '' THEN to_tsquery('french', :tsquery_or)
        ELSE NULL
    END AS q
), text_hits AS (
    SELECT c.id, ROW_NUMBER() OVER (ORDER BY ts_rank_cd(to_tsvector('french', c.content), query.q) DESC) AS rank
    FROM book_chunks c
    JOIN current_book b ON b.version_id = c.version_id
    CROSS JOIN text_query query
    WHERE query.q IS NOT NULL AND to_tsvector('french', c.content) @@ query.q
    ORDER BY ts_rank_cd(to_tsvector('french', c.content), query.q) DESC
    LIMIT 30
), name_ranked AS (
    SELECT c.id, ROW_NUMBER() OVER (ORDER BY MAX(word_similarity(n.token, c.content)) DESC) AS rank
    FROM book_chunks c
    JOIN current_book b ON b.version_id = c.version_id
    JOIN name_tokens n ON TRUE
    WHERE word_similarity(n.token, c.content) >= 0.32
       OR strpos(lower(c.content), n.token) > 0
    GROUP BY c.id
), name_hits AS (
    SELECT id, rank FROM name_ranked WHERE rank <= 30
), subject_boost AS (
    SELECT c.id,
           CASE
               WHEN :subject = 'author' AND (
                    c.content ILIKE '%mes chers enfants%'
                    OR c.content ILIKE '%A mes chers enfants%'
               ) THEN 0.08
               WHEN :subject = 'author' AND c.content ILIKE '%Prophète KADIMA%'
                    AND c.content ILIKE '%enfant%'
                    AND c.start_page > 15
               THEN -0.05
               WHEN :subject = 'prophet' AND (
                    c.content ILIKE '%père d''Anastasie%'
                    OR c.content ILIKE '%eut le bonheur d''être ainsi, pendant cette période, le père%'
                    OR (c.start_page BETWEEN 38 AND 55 AND (
                        c.content ILIKE '%enfant%' OR c.content ILIKE '%père d%' OR c.content ILIKE '%Anastasie%' OR c.content ILIKE '%naquirent%'
                    ))
                    OR (
                        c.content ILIKE '%Prophète KADIMA%'
                        AND (
                            c.content ILIKE '%enfant%'
                            OR c.content ILIKE '%Anastasie%'
                            OR c.content ILIKE '%naquirent%'
                        )
                    )
               ) THEN 0.15
               WHEN :subject = 'prophet' AND c.content ILIKE '%mes chers enfants%'
               THEN -0.06
               ELSE 0.0
           END AS boost
    FROM book_chunks c
    JOIN current_book b ON b.version_id = c.version_id
), fused AS (
    SELECT id, SUM(score) AS score FROM (
        SELECT id, 1.0 / (50 + rank) AS score FROM text_hits
        UNION ALL SELECT id, 1.0 / (40 + rank) AS score FROM name_hits
        UNION ALL SELECT id, boost AS score FROM subject_boost WHERE boost <> 0
    ) ranked GROUP BY id
)
SELECT c.id, c.start_page, c.end_page, c.content, f.score
FROM fused f JOIN book_chunks c ON c.id = f.id
ORDER BY f.score DESC LIMIT :limit
SQL, [
                'slug' => $bookSlug,
                'tsquery_and' => $tsqueryAnd,
                'tsquery_or' => $tsqueryOr,
                'tokens_json' => $tokensJson,
                'subject' => $subject,
                'limit' => $limit,
            ], ['limit' => \Doctrine\DBAL\ParameterType::INTEGER]);
        }

        return $this->connection->fetchAllAssociative(<<<'SQL'
WITH current_book AS (
    SELECT b.current_version_id AS version_id FROM books b WHERE b.slug = :slug AND b.status = 'ACTIVE'
), name_tokens AS (
    SELECT lower(token) AS token
    FROM json_array_elements_text(CAST(:tokens_json AS json)) AS token
    WHERE length(token) >= 4
), vector_ranked AS (
    SELECT c.id,
           c.embedding <=> CAST(:embedding AS vector) AS dist,
           ROW_NUMBER() OVER (ORDER BY c.embedding <=> CAST(:embedding AS vector)) AS rank
    FROM book_chunks c JOIN current_book b ON b.version_id = c.version_id
    WHERE c.embedding IS NOT NULL
    ORDER BY c.embedding <=> CAST(:embedding AS vector)
    LIMIT 30
), vector_hits AS (
    SELECT id, rank FROM vector_ranked
), vector_bonus AS (
    SELECT id, (1.0 / (1.0 + dist)) * 0.02 AS score
    FROM vector_ranked
    WHERE rank <= 5
), text_query AS (
    SELECT CASE
        WHEN :tsquery_and <> '' AND EXISTS (
            SELECT 1
            FROM book_chunks c
            JOIN current_book b ON b.version_id = c.version_id
            WHERE to_tsvector('french', c.content) @@ to_tsquery('french', :tsquery_and)
        ) THEN to_tsquery('french', :tsquery_and)
        WHEN :tsquery_or <> '' THEN to_tsquery('french', :tsquery_or)
        ELSE NULL
    END AS q
), text_hits AS (
    SELECT c.id, ROW_NUMBER() OVER (ORDER BY ts_rank_cd(to_tsvector('french', c.content), query.q) DESC) AS rank
    FROM book_chunks c
    JOIN current_book b ON b.version_id = c.version_id
    CROSS JOIN text_query query
    WHERE query.q IS NOT NULL AND to_tsvector('french', c.content) @@ query.q
    ORDER BY ts_rank_cd(to_tsvector('french', c.content), query.q) DESC
    LIMIT 30
), name_ranked AS (
    SELECT c.id, ROW_NUMBER() OVER (ORDER BY MAX(word_similarity(n.token, c.content)) DESC) AS rank
    FROM book_chunks c
    JOIN current_book b ON b.version_id = c.version_id
    JOIN name_tokens n ON TRUE
    WHERE word_similarity(n.token, c.content) >= 0.32
       OR strpos(lower(c.content), n.token) > 0
    GROUP BY c.id
), name_hits AS (
    SELECT id, rank FROM name_ranked WHERE rank <= 30
), subject_boost AS (
    SELECT c.id,
           CASE
               WHEN :subject = 'author' AND (
                    c.content ILIKE '%mes chers enfants%'
                    OR c.content ILIKE '%A mes chers enfants%'
               ) THEN 0.08
               WHEN :subject = 'author' AND c.content ILIKE '%Prophète KADIMA%'
                    AND c.content ILIKE '%enfant%'
                    AND c.start_page > 15
               THEN -0.05
               WHEN :subject = 'prophet' AND (
                    c.content ILIKE '%père d''Anastasie%'
                    OR c.content ILIKE '%eut le bonheur d''être ainsi, pendant cette période, le père%'
                    OR (c.start_page BETWEEN 38 AND 55 AND (
                        c.content ILIKE '%enfant%' OR c.content ILIKE '%père d%' OR c.content ILIKE '%Anastasie%' OR c.content ILIKE '%naquirent%'
                    ))
                    OR (
                        c.content ILIKE '%Prophète KADIMA%'
                        AND (
                            c.content ILIKE '%enfant%'
                            OR c.content ILIKE '%Anastasie%'
                            OR c.content ILIKE '%naquirent%'
                        )
                    )
               ) THEN 0.15
               WHEN :subject = 'prophet' AND c.content ILIKE '%mes chers enfants%'
               THEN -0.06
               ELSE 0.0
           END AS boost
    FROM book_chunks c
    JOIN current_book b ON b.version_id = c.version_id
), fused AS (
    SELECT id, SUM(score) AS score FROM (
        SELECT id, 1.0 / (60 + rank) AS score FROM vector_hits
        UNION ALL SELECT id, 1.0 / (50 + rank) AS score FROM text_hits
        UNION ALL SELECT id, 1.0 / (40 + rank) AS score FROM name_hits
        UNION ALL SELECT id, boost AS score FROM subject_boost WHERE boost <> 0
        UNION ALL SELECT id, score FROM vector_bonus
    ) ranked GROUP BY id
)
SELECT c.id, c.start_page, c.end_page, c.content, f.score
FROM fused f JOIN book_chunks c ON c.id = f.id
ORDER BY f.score DESC LIMIT :limit
SQL, [
            'slug' => $bookSlug,
            'embedding' => $literal,
            'tsquery_and' => $tsqueryAnd,
            'tsquery_or' => $tsqueryOr,
            'tokens_json' => $tokensJson,
            'subject' => $subject,
            'limit' => $limit,
        ], ['limit' => \Doctrine\DBAL\ParameterType::INTEGER]);
    }
}
