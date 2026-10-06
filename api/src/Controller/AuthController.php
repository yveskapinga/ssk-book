<?php

namespace App\Controller;

use App\Identity\AuthService;
use App\Identity\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/auth', name: 'api_auth_')]
final class AuthController extends ApiController
{
    public function __construct(private readonly AuthService $authService)
    {
    }

    #[Route('/register', name: 'register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        try {
            $payload = $request->toArray();
            $data = $this->authService->register(
                (string) ($payload['email'] ?? ''),
                (string) ($payload['displayName'] ?? ''),
                (string) ($payload['password'] ?? ''),
            );

            return new JsonResponse(['data' => $data], 201);
        } catch (\Throwable $exception) {
            return $this->failure($exception);
        }
    }

    #[Route('/login', name: 'login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        try {
            $payload = $request->toArray();

            return new JsonResponse(['data' => $this->authService->login(
                (string) ($payload['email'] ?? ''),
                (string) ($payload['password'] ?? ''),
            )]);
        } catch (\Throwable $exception) {
            return $this->failure($exception);
        }
    }

    #[Route('/me', name: 'me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        try {
            $user = $this->getUser();
            if (!$user instanceof User) {
                throw new \RuntimeException('Authenticated user is unavailable.');
            }

            return new JsonResponse(['data' => ['user' => $user->toArray()]]);
        } catch (\Throwable $exception) {
            return $this->failure($exception);
        }
    }

    #[Route('/logout', name: 'logout', methods: ['POST'])]
    public function logout(Request $request): JsonResponse
    {
        try {
            $user = $this->getUser();
            if (!$user instanceof User) {
                throw new \RuntimeException('Authenticated user is unavailable.');
            }
            $rawToken = substr((string) $request->headers->get('Authorization'), 7);
            $this->authService->logout($rawToken, $user);

            return new JsonResponse(null, 204);
        } catch (\Throwable $exception) {
            return $this->failure($exception);
        }
    }

    #[Route('/delete-account', name: 'delete_account', methods: ['POST'])]
    public function deleteAccount(Request $request): JsonResponse
    {
        try {
            $user = $this->getUser();
            if (!$user instanceof User) {
                throw new \RuntimeException('Authenticated user is unavailable.');
            }
            $payload = $request->toArray();
            $confirm = (string) ($payload['confirm'] ?? '');
            if ('DELETE' !== strtoupper(trim($confirm))) {
                throw new \App\Shared\DomainException('Confirmez la suppression en envoyant confirm: "DELETE".');
            }
            $this->authService->deleteAccount($user);

            return new JsonResponse(['data' => ['deleted' => true]], 200);
        } catch (\Throwable $exception) {
            return $this->failure($exception);
        }
    }
}
