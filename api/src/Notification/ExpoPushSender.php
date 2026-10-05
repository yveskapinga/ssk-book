<?php

namespace App\Notification;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class ExpoPushSender
{
    public function __construct(
        private PushTokenRepository $tokens,
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param array{title: string, body: string, data?: array<string, mixed>} $payload
     */
    public function broadcast(array $payload): void
    {
        $this->send($this->tokens->allExpoTokens(), $payload);
    }

    /**
     * @param list<string> $expoTokens
     * @param array{title: string, body: string, data?: array<string, mixed>} $payload
     */
    public function send(array $expoTokens, array $payload): void
    {
        $expoTokens = array_values(array_unique(array_filter($expoTokens)));
        if ([] === $expoTokens) {
            return;
        }

        $messages = [];
        foreach ($expoTokens as $token) {
            $messages[] = [
                'to' => $token,
                'sound' => 'default',
                'title' => $payload['title'],
                'body' => $payload['body'],
                'data' => $payload['data'] ?? new \stdClass(),
            ];
        }

        foreach (array_chunk($messages, 100) as $chunk) {
            try {
                $this->httpClient->request('POST', 'https://exp.host/--/api/v2/push/send', [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Content-Type' => 'application/json',
                    ],
                    'json' => $chunk,
                ]);
            } catch (\Throwable $e) {
                $this->logger->warning('Expo push send failed: '.$e->getMessage());
            }
        }
    }
}
