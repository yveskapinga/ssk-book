<?php

namespace App\Identity;

use Doctrine\DBAL\Connection;

final readonly class UserRepository
{
    public function __construct(private Connection $connection)
    {
    }

    public function findByEmail(string $email): ?User
    {
        $row = $this->connection->fetchAssociative(
            'SELECT id, email, display_name, password_hash, roles, status FROM app_users WHERE email = :email',
            ['email' => $email],
        );

        return false === $row ? null : $this->hydrate($row);
    }

    public function findById(string $id): ?User
    {
        $row = $this->connection->fetchAssociative(
            'SELECT id, email, display_name, password_hash, roles, status FROM app_users WHERE id = :id',
            ['id' => $id],
        );

        return false === $row ? null : $this->hydrate($row);
    }

    public function create(string $id, string $email, string $displayName, string $passwordHash, array $roles = ['ROLE_USER']): User
    {
        $this->connection->insert('app_users', [
            'id' => $id,
            'email' => $email,
            'display_name' => $displayName,
            'password_hash' => $passwordHash,
            'roles' => json_encode($roles, JSON_THROW_ON_ERROR),
        ]);

        return $this->findById($id) ?? throw new \RuntimeException('User creation could not be confirmed.');
    }

    public function list(int $limit, int $offset): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, email, display_name, roles, status, created_at, last_login_at FROM app_users ORDER BY created_at DESC LIMIT :limit OFFSET :offset',
            ['limit' => $limit, 'offset' => $offset],
            ['limit' => \Doctrine\DBAL\ParameterType::INTEGER, 'offset' => \Doctrine\DBAL\ParameterType::INTEGER],
        );

        return array_map(static fn (array $row): array => [
            'id' => $row['id'],
            'email' => $row['email'],
            'displayName' => $row['display_name'],
            'roles' => json_decode($row['roles'], true, 512, JSON_THROW_ON_ERROR),
            'status' => $row['status'],
            'createdAt' => $row['created_at'],
            'lastLoginAt' => $row['last_login_at'],
        ], $rows);
    }

    public function setStatus(string $id, string $status): bool
    {
        return 1 === $this->connection->executeStatement(
            'UPDATE app_users SET status = :status, updated_at = CURRENT_TIMESTAMP WHERE id = :id AND status <> :status',
            ['id' => $id, 'status' => $status],
        );
    }

    public function markLogin(string $id): void
    {
        $this->connection->executeStatement(
            'UPDATE app_users SET last_login_at = CURRENT_TIMESTAMP WHERE id = :id',
            ['id' => $id],
        );
    }

    private function hydrate(array $row): User
    {
        return new User(
            $row['id'],
            $row['email'],
            $row['display_name'],
            $row['password_hash'],
            json_decode($row['roles'], true, 512, JSON_THROW_ON_ERROR),
            $row['status'],
        );
    }
}
