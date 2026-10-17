<?php

namespace App\Book;

use Doctrine\DBAL\Connection;

final readonly class ConversationRepository
{
    public function __construct(private Connection $connection) {}

    public function currentBookId(string $slug): ?string
    {
        $id = $this->connection->fetchOne('SELECT id FROM books WHERE slug = :slug AND current_version_id IS NOT NULL AND status = \'ACTIVE\'', ['slug' => $slug]);
        return false === $id ? null : (string) $id;
    }

    public function create(string $id, string $userId, string $bookId, string $title): void
    {
        $this->connection->insert('conversations', ['id' => $id, 'user_id' => $userId, 'book_id' => $bookId, 'title' => $title]);
    }

    public function belongsTo(string $id, string $userId): bool
    {
        return false !== $this->connection->fetchOne('SELECT 1 FROM conversations WHERE id = :id AND user_id = :user_id', ['id' => $id, 'user_id' => $userId]);
    }

    public function addMessage(string $id, string $conversationId, string $role, string $content, bool $insufficient = false): void
    {
        $this->connection->insert(
            'conversation_messages',
            ['id' => $id, 'conversation_id' => $conversationId, 'role' => $role, 'content' => $content, 'insufficient_evidence' => $insufficient],
            ['insufficient_evidence' => \Doctrine\DBAL\ParameterType::BOOLEAN],
        );
    }

    public function addSource(string $messageId, string $chunkId, int $rank, float $score): void
    {
        $this->connection->insert('message_sources', ['message_id' => $messageId, 'chunk_id' => $chunkId, 'rank' => $rank, 'score' => $score]);
    }
}
