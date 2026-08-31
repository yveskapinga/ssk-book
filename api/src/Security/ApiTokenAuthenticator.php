<?php

namespace App\Security;

use App\Identity\AccessTokenRepository;
use App\Identity\UserRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

final class ApiTokenAuthenticator extends AbstractAuthenticator
{
    public function __construct(private readonly AccessTokenRepository $tokens, private readonly UserRepository $users)
    {
    }

    public function supports(Request $request): ?bool
    {
        return str_starts_with((string) $request->headers->get('Authorization'), 'Bearer ');
    }

    public function authenticate(Request $request): Passport
    {
        $rawToken = substr((string) $request->headers->get('Authorization'), 7);
        $tokenHash = hash('sha256', $rawToken);
        $userId = $this->tokens->findActiveUserId($tokenHash);
        if (null === $userId) {
            throw new AuthenticationException('Jeton invalide ou expiré.');
        }
        $this->tokens->touch($tokenHash);

        return new SelfValidatingPassport(new UserBadge($userId, fn (string $id) => $this->users->findById($id) ?? throw new AuthenticationException()));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse(['error' => ['message' => 'Authentification requise.']], 401);
    }
}
