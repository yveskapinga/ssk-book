<?php

namespace App\Book;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final readonly class QuizRepository
{
    public function __construct(private Connection $connection)
    {
    }

    public function insertQuiz(array $data): void
    {
        $this->connection->insert('quizzes', $data);
    }

    public function insertQuestion(array $data): void
    {
        $this->connection->insert('quiz_questions', $data);
    }

    public function insertChoice(array $data): void
    {
        $this->connection->insert('quiz_choices', $data, ['is_correct' => ParameterType::BOOLEAN]);
    }

    public function find(string $id): ?array
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM quizzes WHERE id = :id', ['id' => $id]);

        return false === $row ? null : $row;
    }

    public function listAdmin(): array
    {
        return $this->connection->fetchAllAssociative(<<<'SQL'
SELECT q.id, q.title, q.status, q.created_at, b.title AS book_title, b.slug,
       (SELECT COUNT(*) FROM quiz_questions qq WHERE qq.quiz_id = q.id) AS question_count
FROM quizzes q
JOIN books b ON b.id = q.book_id
ORDER BY q.created_at DESC
SQL);
    }

    public function listPublished(): array
    {
        return $this->connection->fetchAllAssociative(<<<'SQL'
SELECT q.id, q.title, q.description, q.status, b.title AS book_title, b.slug,
       (SELECT COUNT(*) FROM quiz_questions qq WHERE qq.quiz_id = q.id) AS question_count
FROM quizzes q
JOIN books b ON b.id = q.book_id
WHERE q.status = 'PUBLISHED'
ORDER BY q.title
SQL);
    }

    public function questions(string $quizId, bool $includeCorrect): array
    {
        $questions = $this->connection->fetchAllAssociative(
            'SELECT id, position, prompt, question_type, chunk_id FROM quiz_questions WHERE quiz_id = :id ORDER BY position',
            ['id' => $quizId],
        );
        foreach ($questions as &$question) {
            $sql = $includeCorrect
                ? 'SELECT id, position, label, is_correct FROM quiz_choices WHERE question_id = :id ORDER BY position'
                : 'SELECT id, position, label FROM quiz_choices WHERE question_id = :id ORDER BY position';
            $question['choices'] = $this->connection->fetchAllAssociative($sql, ['id' => $question['id']]);
        }

        return $questions;
    }

    public function questionWithChoices(string $questionId): ?array
    {
        $question = $this->connection->fetchAssociative('SELECT * FROM quiz_questions WHERE id = :id', ['id' => $questionId]);
        if (false === $question) {
            return null;
        }
        $question['choices'] = $this->connection->fetchAllAssociative(
            'SELECT id, position, label, is_correct FROM quiz_choices WHERE question_id = :id ORDER BY position',
            ['id' => $questionId],
        );

        return $question;
    }

    public function submitForReview(string $id): int
    {
        return $this->connection->executeStatement(
            "UPDATE quizzes SET status = 'REVIEW_REQUIRED', updated_at = CURRENT_TIMESTAMP WHERE id = :id AND status = 'DRAFT'",
            ['id' => $id],
        );
    }

    public function publish(string $id): int
    {
        return $this->connection->executeStatement(
            "UPDATE quizzes SET status = 'PUBLISHED', updated_at = CURRENT_TIMESTAMP WHERE id = :id AND status = 'REVIEW_REQUIRED'",
            ['id' => $id],
        );
    }

    public function insertAttempt(array $data): void
    {
        $this->connection->insert('quiz_attempts', $data);
    }

    public function findAttempt(string $id): ?array
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM quiz_attempts WHERE id = :id', ['id' => $id]);

        return false === $row ? null : $row;
    }

    public function insertAnswer(array $data): void
    {
        $this->connection->insert('quiz_answers', $data, [
            'is_correct' => ParameterType::BOOLEAN,
        ]);
    }

    public function completeAttempt(string $id, float $score): void
    {
        $this->connection->executeStatement(
            "UPDATE quiz_attempts SET status = 'COMPLETED', score = :score, completed_at = CURRENT_TIMESTAMP WHERE id = :id AND status = 'IN_PROGRESS'",
            ['id' => $id, 'score' => $score],
        );
    }

    public function attemptsForUser(string $userId): array
    {
        return $this->connection->fetchAllAssociative(<<<'SQL'
SELECT a.id, a.quiz_id, a.status, a.score, a.started_at, a.completed_at, q.title
FROM quiz_attempts a
JOIN quizzes q ON q.id = a.quiz_id
WHERE a.user_id = :user_id
ORDER BY a.started_at DESC
SQL, ['user_id' => $userId]);
    }

    public function ranking(string $quizId): array
    {
        return $this->connection->fetchAllAssociative(<<<'SQL'
SELECT u.display_name, MAX(a.score) AS best_score, COUNT(*) AS attempts
FROM quiz_attempts a
JOIN app_users u ON u.id = a.user_id
WHERE a.quiz_id = :quiz_id AND a.status = 'COMPLETED'
GROUP BY u.id, u.display_name
ORDER BY best_score DESC, attempts ASC
LIMIT 20
SQL, ['quiz_id' => $quizId]);
    }
}
