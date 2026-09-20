<?php

namespace App\Book;

use App\Ai\GatewayAnswerClient;
use App\Ai\GeminiClient;
use App\Identity\User;
use App\Shared\DomainException;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

final readonly class QuestionService
{
    public function __construct(private Connection $connection, private SearchRepository $search, private ConversationRepository $conversations, private GeminiClient $gemini, private GatewayAnswerClient $answers) {}

    public function ask(User $user, string $bookSlug, string $question, ?string $conversationId): array
    {
        $question = trim($question);
        if (mb_strlen($question) < 3 || mb_strlen($question) > 1000) throw new DomainException('La question doit contenir entre 3 et 1 000 caractères.');
        $bookId = $this->conversations->currentBookId($bookSlug);
        if (null === $bookId) throw new DomainException('Aucune version publiée de ce livre n’est disponible.', 404);
        $queryVector = $this->gemini->embed([$question], 'RETRIEVAL_QUERY')[0];
        $sources = $this->search->hybrid($bookSlug, $question, $queryVector);
        if ([] === $sources) $answer = ['answer' => 'Le livre ne permet pas de répondre.', 'sourceIds' => [], 'insufficientEvidence' => true];
        else $answer = $this->answers->answer($question, $sources);

        $allowed = array_column($sources, null, 'id');
        $sourceIds = CitedSourceResolver::resolve($answer['sourceIds'], $sources, (bool) $answer['insufficientEvidence']);
        if (!$answer['insufficientEvidence'] && [] === $sourceIds) {
            throw new DomainException('La réponse n’a pas pu être reliée aux passages du livre. Réessayez.', 502);
        }
        $newConversation = null === $conversationId;
        $conversationId ??= Uuid::v7()->toRfc4122();
        if (!$newConversation && !$this->conversations->belongsTo($conversationId, $user->id)) throw new DomainException('Conversation introuvable.', 404);
        $assistantMessageId = Uuid::v7()->toRfc4122();
        $this->connection->transactional(function () use ($user, $bookId, $question, $conversationId, $newConversation, $assistantMessageId, $answer, $sourceIds, $allowed): void {
            if ($newConversation) {
                $this->conversations->create($conversationId, $user->id, $bookId, mb_substr($question, 0, 160));
            }
            $this->conversations->addMessage(Uuid::v7()->toRfc4122(), $conversationId, 'USER', $question);
            $this->conversations->addMessage($assistantMessageId, $conversationId, 'ASSISTANT', (string) $answer['answer'], (bool) $answer['insufficientEvidence']);
            foreach ($sourceIds as $index => $sourceId) $this->conversations->addSource($assistantMessageId, $sourceId, $index + 1, (float) $allowed[$sourceId]['score']);
        });
        return ['conversationId' => $conversationId, 'answer' => $answer['answer'], 'insufficientEvidence' => (bool) $answer['insufficientEvidence'], 'sources' => array_values(array_map(static fn ($id): array => $allowed[$id], $sourceIds))];
    }
}
