<?php

namespace App\Controller;

use App\Identity\User;
use App\Notification\PushTokenRepository;
use App\Shared\DomainException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/api/me/push-tokens', name: 'api_me_push_tokens_')]
final class PushTokenController extends ApiController
{
    public function __construct(private readonly PushTokenRepository $tokens)
    {
    }

    #[Route('', name: 'upsert', methods: ['POST'])]
    public function upsert(Request $request): JsonResponse
    {
        try {
            $user = $this->actor();
            $payload = $request->toArray();
            $token = trim((string) ($payload['token'] ?? ''));
            $platform = strtolower(trim((string) ($payload['platform'] ?? 'android')));
            if ('' === $token || (!str_starts_with($token, 'ExponentPushToken') && !str_starts_with($token, 'ExpoPushToken'))) {
                throw new DomainException('Jeton push Expo invalide.');
            }
            if (!\in_array($platform, ['android', 'ios', 'web'], true)) {
                throw new DomainException('Plateforme push invalide.');
            }
            $this->tokens->upsert(Uuid::v7()->toRfc4122(), $user->id, $token, $platform);

            return $this->success(['ok' => true]);
        } catch (\Throwable $exception) {
            return $this->failure($exception);
        }
    }

    #[Route('', name: 'delete', methods: ['DELETE'])]
    public function delete(Request $request): JsonResponse
    {
        try {
            $user = $this->actor();
            $payload = $request->toArray();
            $token = trim((string) ($payload['token'] ?? ''));
            if ('' !== $token) {
                $this->tokens->deleteForUser($user->id, $token);
            }

            return new JsonResponse(null, 204);
        } catch (\Throwable $exception) {
            return $this->failure($exception);
        }
    }

    private function actor(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new \RuntimeException('Authenticated user is unavailable.');
        }

        return $user;
    }
}
