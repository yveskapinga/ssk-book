<?php

namespace App\Book;

use Doctrine\DBAL\Connection;

final readonly class IngestionJobRepository
{
    public function __construct(private Connection $connection)
    {
    }

    public function create(string $id, string $versionId): void
    {
        $this->connection->insert('ingestion_jobs', ['id' => $id, 'version_id' => $versionId]);
    }

    public function start(string $id, string $step): void
    {
        $this->connection->executeStatement(
            "UPDATE ingestion_jobs SET status = 'RUNNING', current_step = :step, progress = 5, started_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = :id AND status = 'PENDING'",
            ['id' => $id, 'step' => $step],
        );
    }

    public function progress(string $id, string $step, int $progress, array $metrics = []): void
    {
        $this->connection->executeStatement(
            'UPDATE ingestion_jobs SET current_step = :step, progress = :progress, metrics = CAST(:metrics AS jsonb), updated_at = CURRENT_TIMESTAMP WHERE id = :id',
            ['id' => $id, 'step' => $step, 'progress' => $progress, 'metrics' => json_encode($metrics, JSON_THROW_ON_ERROR)],
        );
    }

    public function complete(string $id, array $metrics): void
    {
        $this->connection->executeStatement(
            "UPDATE ingestion_jobs SET status = 'COMPLETED', current_step = 'REVIEW_REQUIRED', progress = 100, metrics = CAST(:metrics AS jsonb), completed_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = :id",
            ['id' => $id, 'metrics' => json_encode($metrics, JSON_THROW_ON_ERROR)],
        );
    }

    public function reopenFailed(string $id): int
    {
        return $this->connection->executeStatement(
            "UPDATE ingestion_jobs SET status = 'PENDING', current_step = 'PENDING', progress = 0, error_message = NULL, started_at = NULL, completed_at = NULL, updated_at = CURRENT_TIMESTAMP WHERE id = :id AND status = 'FAILED'",
            ['id' => $id],
        );
    }

    public function fail(string $id, string $message): void
    {
        $this->connection->executeStatement(
            "UPDATE ingestion_jobs SET status = 'FAILED', current_step = 'FAILED', error_message = :message, completed_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = :id",
            ['id' => $id, 'message' => mb_substr($message, 0, 4000)],
        );
    }

    public function find(string $id): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT id, version_id, status, current_step, progress, error_message, metrics, started_at, completed_at, created_at FROM ingestion_jobs WHERE id = :id',
            ['id' => $id],
        );

        if (false === $row) {
            return null;
        }
        $row['metrics'] = json_decode($row['metrics'], true, 512, JSON_THROW_ON_ERROR);

        return $row;
    }
}
