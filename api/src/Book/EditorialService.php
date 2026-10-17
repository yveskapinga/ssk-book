<?php

namespace App\Book;

use App\Audit\AuditRepository;
use App\Identity\User;
use App\Shared\DomainException;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

final readonly class EditorialService
{
    public function __construct(private Connection $connection, private EditorialRepository $repository, private AuditRepository $audit)
    {
    }

    public function list(int $page, int $limit): array
    {
        $page = max(1, $page); $limit = max(1, min(100, $limit));
        return ['items' => $this->repository->listVersions($limit, ($page - 1) * $limit), 'page' => $page, 'limit' => $limit];
    }

    public function chunks(string $versionId, int $page, int $limit): array
    {
        $page = max(1, $page); $limit = max(1, min(100, $limit));
        return ['items' => $this->repository->chunks($versionId, $limit, ($page - 1) * $limit), 'page' => $page, 'limit' => $limit];
    }

    public function decide(User $reviewer, string $versionId, string $decision, ?string $notes): void
    {
        $decision = strtoupper(trim($decision)); $notes = null === $notes ? null : trim($notes);
        if (!in_array($decision, ['APPROVED', 'REJECTED'], true)) throw new DomainException('Décision de revue invalide.');
        $summary = $this->repository->reviewSummary($versionId);
        if ([] === $summary) throw new DomainException('Version introuvable.', 404);
        if ('REVIEW_REQUIRED' !== $summary['status']) throw new DomainException('Cette version n’est pas en attente de revue.', 409);
        if ('REJECTED' === $decision && (!$notes || mb_strlen($notes) < 5)) throw new DomainException('Le motif du rejet est obligatoire.');

        $this->connection->transactional(function () use ($reviewer, $versionId, $decision, $notes): void {
            $this->repository->addReview(Uuid::v7()->toRfc4122(), $versionId, $reviewer->id, $decision, $notes);
            if ('REJECTED' === $decision) $this->repository->reject($versionId);
            $this->audit->append($reviewer->id, 'BOOK_VERSION_REVIEWED', 'BOOK_VERSION', $versionId, ['decision' => $decision, 'notes' => $notes]);
        });
    }

    public function publish(User $publisher, string $versionId): void
    {
        $summary = $this->repository->reviewSummary($versionId);
        if ([] === $summary) throw new DomainException('Version introuvable.', 404);
        if ('REVIEW_REQUIRED' !== $summary['status'] || 'APPROVED' !== $summary['latest_decision']) throw new DomainException('La version doit être approuvée avant publication.', 409);
        if ((int) $summary['chunk_count'] < 1 || (int) $summary['embedded_count'] !== (int) $summary['chunk_count']) throw new DomainException('Tous les passages doivent être indexés avant publication.', 409);

        $this->connection->transactional(function () use ($publisher, $versionId, $summary): void {
            $this->repository->publish($versionId, $summary['book_id']);
            $this->audit->append($publisher->id, 'BOOK_VERSION_PUBLISHED', 'BOOK_VERSION', $versionId);
        });
    }
}
