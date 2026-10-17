<?php

namespace App\Book;

use App\Audit\AuditRepository;
use App\Identity\User;
use App\Notification\ExpoPushSender;
use App\Shared\DomainException;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

final readonly class QuizService
{
    public function __construct(
        private Connection $connection,
        private QuizRepository $quizzes,
        private BookRepository $books,
        private AuditRepository $audit,
        private ExpoPushSender $push,
    ) {
    }

    public function create(User $actor, string $bookSlug, string $title, ?string $description, array $questions): array
    {
        $title = trim($title);
        if (mb_strlen($title) < 2) {
            throw new DomainException('Le titre du quiz est invalide.');
        }
        if ([] === $questions) {
            throw new DomainException('Un quiz doit contenir au moins une question.');
        }
        $book = $this->books->publishedBySlug($bookSlug);
        if (null === $book) {
            $book = $this->books->findBySlug($bookSlug);
        }
        if (null === $book) {
            throw new DomainException('Livre introuvable.', 404);
        }
        $versionId = $book['version_id'] ?? $book['current_version_id'] ?? $this->books->latestVersionId($book['id']);
        if (null === $versionId || '' === $versionId) {
            throw new DomainException('Le quiz nécessite une version du livre.', 409);
        }

        $normalized = $this->normalizeQuestions($questions);
        $quizId = Uuid::v7()->toRfc4122();
        $this->connection->transactional(function () use ($actor, $book, $versionId, $title, $description, $normalized, $quizId): void {
            $this->quizzes->insertQuiz([
                'id' => $quizId,
                'book_id' => $book['id'],
                'version_id' => $versionId,
                'title' => $title,
                'description' => $description ? trim($description) : null,
                'status' => 'DRAFT',
                'created_by' => $actor->id,
            ]);
            foreach ($normalized as $question) {
                $this->quizzes->insertQuestion([
                    'id' => $question['id'],
                    'quiz_id' => $quizId,
                    'position' => $question['position'],
                    'prompt' => $question['prompt'],
                    'question_type' => $question['type'],
                    'chunk_id' => $question['chunkId'],
                ]);
                foreach ($question['choices'] as $choice) {
                    $this->quizzes->insertChoice($choice);
                }
            }
            $this->audit->append($actor->id, 'QUIZ_CREATED', 'QUIZ', $quizId, ['title' => $title]);
        });

        return ['id' => $quizId, 'status' => 'DRAFT'];
    }

    public function submitForReview(User $actor, string $quizId): void
    {
        $quiz = $this->quizzes->find($quizId);
        if (null === $quiz) {
            throw new DomainException('Quiz introuvable.', 404);
        }
        if ([] === $this->quizzes->questions($quizId, false)) {
            throw new DomainException('Un quiz soumis à revue doit contenir des questions.', 409);
        }
        if (1 !== $this->quizzes->submitForReview($quizId)) {
            throw new DomainException('Seuls les quiz en brouillon peuvent être soumis à revue.', 409);
        }
        $this->audit->append($actor->id, 'QUIZ_REVIEW_REQUESTED', 'QUIZ', $quizId);
    }

    public function publish(User $actor, string $quizId): void
    {
        $quiz = $this->quizzes->find($quizId);
        if (null === $quiz) {
            throw new DomainException('Quiz introuvable.', 404);
        }
        $questions = $this->quizzes->questions($quizId, true);
        if ([] === $questions) {
            throw new DomainException('Un quiz publié doit contenir des questions.', 409);
        }
        foreach ($questions as $question) {
            if ('SHORT_ANSWER' === $question['question_type']) {
                continue;
            }
            $correct = array_filter(
                $question['choices'],
                static fn (array $choice): bool => true === $choice['is_correct'] || 't' === $choice['is_correct'] || 1 === $choice['is_correct'] || '1' === $choice['is_correct'],
            );
            if (1 !== count($correct)) {
                throw new DomainException('Chaque question fermée doit avoir exactement une bonne réponse.', 409);
            }
        }
        if (1 !== $this->quizzes->publish($quizId)) {
            throw new DomainException('Un quiz ne peut être publié qu’après une revue humaine.', 409);
        }
        $this->audit->append($actor->id, 'QUIZ_PUBLISHED', 'QUIZ', $quizId);
        $this->push->broadcast([
            'title' => 'Nouveau quiz',
            'body' => (string) ($quiz['title'] ?? 'Un quiz vient d’être publié.'),
            'data' => ['type' => 'quiz', 'quizId' => $quizId, 'url' => '/quiz'],
        ]);
    }

    public function adminList(): array
    {
        return ['items' => $this->quizzes->listAdmin()];
    }

    public function published(): array
    {
        return ['items' => $this->quizzes->listPublished()];
    }

    public function readerDetail(string $quizId): array
    {
        $quiz = $this->quizzes->find($quizId);
        if (null === $quiz || 'PUBLISHED' !== $quiz['status']) {
            throw new DomainException('Quiz introuvable.', 404);
        }

        return ['quiz' => $quiz, 'questions' => $this->quizzes->questions($quizId, false)];
    }

    public function startAttempt(User $user, string $quizId): array
    {
        $quiz = $this->quizzes->find($quizId);
        if (null === $quiz || 'PUBLISHED' !== $quiz['status']) {
            throw new DomainException('Quiz introuvable.', 404);
        }
        $id = Uuid::v7()->toRfc4122();
        $this->quizzes->insertAttempt([
            'id' => $id,
            'quiz_id' => $quizId,
            'user_id' => $user->id,
            'status' => 'IN_PROGRESS',
        ]);

        return ['attemptId' => $id, 'questions' => $this->quizzes->questions($quizId, false)];
    }

    public function submit(User $user, string $attemptId, array $answers): array
    {
        $attempt = $this->quizzes->findAttempt($attemptId);
        if (null === $attempt || $attempt['user_id'] !== $user->id) {
            throw new DomainException('Tentative introuvable.', 404);
        }
        if ('IN_PROGRESS' !== $attempt['status']) {
            throw new DomainException('Cette tentative est déjà close.', 409);
        }
        $questions = $this->quizzes->questions($attempt['quiz_id'], true);
        if (count($answers) !== count($questions)) {
            throw new DomainException('Toutes les questions doivent être répondues.');
        }
        $correct = 0;
        $this->connection->transactional(function () use ($attemptId, $questions, $answers, &$correct): void {
            $byQuestion = [];
            foreach ($answers as $answer) {
                $byQuestion[(string) ($answer['questionId'] ?? '')] = $answer;
            }
            foreach ($questions as $question) {
                $payload = $byQuestion[$question['id']] ?? null;
                if (null === $payload) {
                    throw new DomainException('Réponse manquante.');
                }
                $isCorrect = $this->grade($question, $payload);
                if ($isCorrect) {
                    ++$correct;
                }
                $this->quizzes->insertAnswer([
                    'id' => Uuid::v7()->toRfc4122(),
                    'attempt_id' => $attemptId,
                    'question_id' => $question['id'],
                    'choice_id' => $payload['choiceId'] ?? null,
                    'short_answer' => isset($payload['shortAnswer']) ? trim((string) $payload['shortAnswer']) : null,
                    'is_correct' => $isCorrect,
                ]);
            }
            $score = round(100 * $correct / max(1, count($questions)), 2);
            $this->quizzes->completeAttempt($attemptId, $score);
        });
        $completed = $this->quizzes->findAttempt($attemptId);

        return [
            'attemptId' => $attemptId,
            'score' => (float) ($completed['score'] ?? 0),
            'correct' => $correct,
            'total' => count($questions),
        ];
    }

    public function history(User $user): array
    {
        return ['items' => $this->quizzes->attemptsForUser($user->id)];
    }

    public function ranking(string $quizId): array
    {
        $quiz = $this->quizzes->find($quizId);
        if (null === $quiz || 'PUBLISHED' !== $quiz['status']) {
            throw new DomainException('Quiz introuvable.', 404);
        }

        return ['items' => $this->quizzes->ranking($quizId)];
    }

    private function normalizeQuestions(array $questions): array
    {
        $normalized = [];
        foreach (array_values($questions) as $index => $raw) {
            $prompt = trim((string) ($raw['prompt'] ?? ''));
            $type = strtoupper((string) ($raw['type'] ?? 'MULTIPLE_CHOICE'));
            if (mb_strlen($prompt) < 3) {
                throw new DomainException('Une question est trop courte.');
            }
            if (!in_array($type, ['MULTIPLE_CHOICE', 'TRUE_FALSE', 'SHORT_ANSWER'], true)) {
                throw new DomainException('Type de question invalide.');
            }
            $questionId = Uuid::v7()->toRfc4122();
            $choices = [];
            if ('SHORT_ANSWER' !== $type) {
                $rawChoices = $raw['choices'] ?? [];
                if (!is_array($rawChoices) || count($rawChoices) < 2) {
                    throw new DomainException('Une question fermée doit avoir au moins deux choix.');
                }
                $correct = 0;
                foreach (array_values($rawChoices) as $choiceIndex => $choice) {
                    $isCorrect = (bool) ($choice['isCorrect'] ?? false);
                    if ($isCorrect) {
                        ++$correct;
                    }
                    $choices[] = [
                        'id' => Uuid::v7()->toRfc4122(),
                        'question_id' => $questionId,
                        'position' => $choiceIndex,
                        'label' => trim((string) ($choice['label'] ?? '')),
                        'is_correct' => $isCorrect,
                    ];
                }
                if (1 !== $correct) {
                    throw new DomainException('Chaque question fermée doit avoir exactement une bonne réponse.');
                }
            }
            $normalized[] = [
                'id' => $questionId,
                'position' => $index,
                'prompt' => $prompt,
                'type' => $type,
                'chunkId' => isset($raw['chunkId']) ? (string) $raw['chunkId'] : null,
                'choices' => $choices,
            ];
        }

        return $normalized;
    }

    private function grade(array $question, array $payload): bool
    {
        if ('SHORT_ANSWER' === $question['question_type']) {
            return false;
        }
        $choiceId = (string) ($payload['choiceId'] ?? '');
        foreach ($question['choices'] as $choice) {
            if ($choice['id'] === $choiceId) {
                return true === $choice['is_correct'] || 't' === $choice['is_correct'] || 1 === $choice['is_correct'] || '1' === $choice['is_correct'];
            }
        }

        return false;
    }
}
