<?php

namespace App\Identity;

use App\Audit\AuditRepository;
use App\Shared\DomainException;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

final readonly class AuthService
{
    public function __construct(
        private Connection $connection,
        private UserRepository $users,
        private AccessTokenRepository $tokens,
        private AuditRepository $audit,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function register(string $email, string $displayName, string $plainPassword, array $roles = ['ROLE_USER']): array
    {
        $email = mb_strtolower(trim($email));
        $displayName = trim($displayName);
        $this->validateCredentials($email, $displayName, $plainPassword);

        try {
            return $this->connection->transactional(function () use ($email, $displayName, $plainPassword, $roles): array {
                $id = Uuid::v7()->toRfc4122();
                $roles = array_values(array_unique([...$roles, 'ROLE_USER']));
                $prototype = new User($id, $email, $displayName, '', $roles, 'ACTIVE');
                $user = $this->users->create($id, $email, $displayName, $this->passwordHasher->hashPassword($prototype, $plainPassword), $roles);
                $this->audit->append($id, 'USER_REGISTERED', 'USER', $id);

                return $this->issueToken($user);
            });
        } catch (UniqueConstraintViolationException) {
            throw new DomainException('Cette adresse e-mail est déjà utilisée.', 409);
        }
    }

    public function login(string $email, string $plainPassword): array
    {
        $email = mb_strtolower(trim($email));
        $user = $this->users->findByEmail($email);

        if (null === $user || !$this->passwordHasher->isPasswordValid($user, $plainPassword)) {
            throw new DomainException('Identifiants invalides.', 401);
        }
        if ('ACTIVE' !== $user->status) {
            throw new DomainException('Ce compte est suspendu.', 403);
        }

        return $this->connection->transactional(function () use ($user): array {
            $this->users->markLogin($user->id);
            $this->audit->append($user->id, 'USER_LOGGED_IN', 'USER', $user->id);

            return $this->issueToken($user);
        });
    }

    public function logout(string $rawToken, User $user): void
    {
        $this->connection->transactional(function () use ($rawToken, $user): void {
            $this->tokens->revoke(hash('sha256', $rawToken));
            $this->audit->append($user->id, 'USER_LOGGED_OUT', 'USER', $user->id);
        });
    }

    private function issueToken(User $user): array
    {
        $rawToken = bin2hex(random_bytes(32));
        $expiresAt = new \DateTimeImmutable('+7 days');
        $this->tokens->create(Uuid::v7()->toRfc4122(), $user->id, hash('sha256', $rawToken), $expiresAt);

        return ['token' => $rawToken, 'expiresAt' => $expiresAt->format(DATE_ATOM), 'user' => $user->toArray()];
    }

    private function validateCredentials(string $email, string $displayName, string $password): void
    {
        if (false === filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new DomainException('Adresse e-mail invalide.');
        }
        if (mb_strlen($displayName) < 2 || mb_strlen($displayName) > 120) {
            throw new DomainException('Le nom doit contenir entre 2 et 120 caractères.');
        }
        if (mb_strlen($password) < 10) {
            throw new DomainException('Le mot de passe doit contenir au moins 10 caractères.');
        }
    }
}
