<?php

namespace App\Notification;

use Doctrine\DBAL\Connection;

final readonly class PushTokenRepository
{
    public function __construct(private Connection $connection)
    {
    }

    public function upsert(string $id, string $userId, string $expoToken, string $platform): void
    {
        $this->connection->executeStatement(
            <<<'SQL'
INSERT INTO user_push_tokens (id, user_id, expo_token, platform, created_at, updated_at)
VALUES (:id, :user_id, :expo_token, :platform, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
ON CONFLICT (expo_token) DO UPDATE SET
  user_id = EXCLUDED.user_id,
  platform = EXCLUDED.platform,
  updated_at = CURRENT_TIMESTAMP
SQL,
            [
                'id' => $id,
                'user_id' => $userId,
                'expo_token' => $expoToken,
                'platform' => $platform,
            ],
        );
    }

    public function deleteForUser(string $userId, string $expoToken): void
    {
        $this->connection->executeStatement(
            'DELETE FROM user_push_tokens WHERE user_id = :user_id AND expo_token = :expo_token',
            ['user_id' => $userId, 'expo_token' => $expoToken],
        );
    }

    /** @return list<string> */
    public function allExpoTokens(): array
    {
        $rows = $this->connection->fetchFirstColumn('SELECT expo_token FROM user_push_tokens');

        return array_values(array_map('strval', $rows));
    }

    /** @return list<string> */
    public function tokensForUsers(array $userIds): array
    {
        if ([] === $userIds) {
            return [];
        }
        $params = [];
        $placeholders = [];
        foreach (array_values($userIds) as $i => $id) {
            $key = 'u'.$i;
            $placeholders[] = ':'.$key;
            $params[$key] = $id;
        }
        $rows = $this->connection->fetchFirstColumn(
            'SELECT expo_token FROM user_push_tokens WHERE user_id IN ('.implode(', ', $placeholders).')',
            $params,
        );

        return array_values(array_map('strval', $rows));
    }
}
