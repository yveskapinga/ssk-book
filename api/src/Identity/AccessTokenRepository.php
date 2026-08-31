<?php

namespace App\Identity;

use Doctrine\DBAL\Connection;

final readonly class AccessTokenRepository
{
    public function __construct(private Connection $connection)
    {
    }

    public function create(string $id, string $userId, string $tokenHash, \DateTimeImmutable $expiresAt): void
    {
        $this->connection->insert('user_access_tokens', [
            'id' => $id,
            'user_id' => $userId,
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt->format('Y-m-d H:i:sP'),
        ]);
    }

    public function findActiveUserId(string $tokenHash): ?string
    {
        $userId = $this->connection->fetchOne(
            <<<'SQL'
SELECT t.user_id
FROM user_access_tokens t
JOIN app_users u ON u.id = t.user_id
WHERE t.token_hash = :token_hash
  AND t.revoked_at IS NULL
  AND t.expires_at > CURRENT_TIMESTAMP
  AND u.status = 'ACTIVE'
SQL,
            ['token_hash' => $tokenHash],
        );

        return false === $userId ? null : (string) $userId;
    }

    public function touch(string $tokenHash): void
    {
        $this->connection->executeStatement(
            'UPDATE user_access_tokens SET last_used_at = CURRENT_TIMESTAMP WHERE token_hash = :token_hash',
            ['token_hash' => $tokenHash],
        );
    }

    public function revoke(string $tokenHash): void
    {
        $this->connection->executeStatement(
            'UPDATE user_access_tokens SET revoked_at = CURRENT_TIMESTAMP WHERE token_hash = :token_hash AND revoked_at IS NULL',
            ['token_hash' => $tokenHash],
        );
    }

    public function revokeAllForUser(string $userId): void
    {
        $this->connection->executeStatement(
            'UPDATE user_access_tokens SET revoked_at = CURRENT_TIMESTAMP WHERE user_id = :user_id AND revoked_at IS NULL',
            ['user_id' => $userId],
        );
    }
}
