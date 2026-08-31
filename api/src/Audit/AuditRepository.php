<?php

namespace App\Audit;

use Doctrine\DBAL\Connection;

final readonly class AuditRepository
{
    public function __construct(private Connection $connection)
    {
    }

    public function append(?string $actorUserId, string $action, string $subjectType, ?string $subjectId, array $context = []): void
    {
        $this->connection->insert('audit_logs', [
            'actor_user_id' => $actorUserId,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'context' => json_encode($context, JSON_THROW_ON_ERROR),
        ]);
    }
}
