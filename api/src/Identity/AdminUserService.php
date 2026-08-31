<?php

namespace App\Identity;

use App\Audit\AuditRepository;
use App\Shared\DomainException;
use Doctrine\DBAL\Connection;

final readonly class AdminUserService
{
    public function __construct(
        private Connection $connection,
        private UserRepository $users,
        private AccessTokenRepository $tokens,
        private AuditRepository $audit,
    ) {
    }

    public function list(int $page, int $limit): array
    {
        $page = max(1, $page);
        $limit = max(1, min(100, $limit));

        return ['items' => $this->users->list($limit, ($page - 1) * $limit), 'page' => $page, 'limit' => $limit];
    }

    public function changeStatus(User $actor, string $userId, string $status): void
    {
        $status = strtoupper(trim($status));
        if (!in_array($status, ['ACTIVE', 'SUSPENDED'], true)) {
            throw new DomainException('Statut utilisateur invalide.');
        }
        if ($actor->id === $userId && 'SUSPENDED' === $status) {
            throw new DomainException('Un administrateur ne peut pas suspendre son propre compte.', 409);
        }
        if (null === $this->users->findById($userId)) {
            throw new DomainException('Utilisateur introuvable.', 404);
        }

        $this->connection->transactional(function () use ($actor, $userId, $status): void {
            $changed = $this->users->setStatus($userId, $status);
            if (!$changed) {
                return;
            }
            if ('SUSPENDED' === $status) {
                $this->tokens->revokeAllForUser($userId);
            }
            $this->audit->append($actor->id, 'USER_STATUS_CHANGED', 'USER', $userId, ['status' => $status]);
        });
    }
}
