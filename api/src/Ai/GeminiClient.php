<?php

namespace App\Ai;

use App\Shared\DomainException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class GeminiClient
{
    public function __construct(
        private HttpClientInterface $http,
        #[Autowire('%env(GEMINI_API_KEY)%')] private string $apiKey,
        #[Autowire('%env(GEMINI_EMBEDDING_MODEL)%')] private string $embeddingModel,
        #[Autowire('%env(int:GEMINI_EMBEDDING_DIMENSION)%')] private int $embeddingDimension,
    ) {
    }

    public function embeddingModel(): string
    {
        return $this->embeddingModel;
    }

    public function embed(array $texts, string $taskType = 'RETRIEVAL_DOCUMENT'): array
    {
        $this->assertConfigured();
        $model = 'models/'.$this->embeddingModel;
        $requests = array_map(fn (string $text): array => [
            'model' => $model,
            'content' => ['parts' => [['text' => $text]]],
            'taskType' => $taskType,
            'outputDimensionality' => $this->embeddingDimension,
        ], $texts);
        $data = $this->requestJson("https://generativelanguage.googleapis.com/v1beta/{$model}:batchEmbedContents", [
            'headers' => ['x-goog-api-key' => $this->apiKey],
            'json' => ['requests' => $requests],
            'timeout' => 180,
        ]);
        $vectors = array_map(static fn (array $item): array => $item['values'] ?? [], $data['embeddings'] ?? []);
        if (count($vectors) !== count($texts)) {
            throw new \RuntimeException('Gemini returned an incomplete embedding batch.');
        }
        foreach ($vectors as $vector) {
            if (count($vector) !== $this->embeddingDimension) {
                throw new \RuntimeException('Gemini returned an unexpected embedding dimension.');
            }
        }

        return $vectors;
    }

    private function requestJson(string $url, array $options, int $attempts = 6): array
    {
        $delay = 2;
        $last = null;
        for ($attempt = 1; $attempt <= $attempts; ++$attempt) {
            try {
                return $this->http->request('POST', $url, $options)->toArray();
            } catch (HttpExceptionInterface $exception) {
                $last = $exception;
                $status = $exception->getResponse()->getStatusCode();
                if (!in_array($status, [429, 500, 503], true) || $attempt === $attempts) {
                    break;
                }
                $retryAfter = $exception->getResponse()->getHeaders(false)['retry-after'][0] ?? null;
                $wait = is_numeric($retryAfter) ? max(1, (int) $retryAfter) : $delay;
                sleep($wait);
                $delay = min(60, $delay * 2);
            }
        }
        $status = $last instanceof HttpExceptionInterface ? $last->getResponse()->getStatusCode() : 0;
        if (404 === $status) {
            throw new DomainException('Le modèle d’embedding Gemini configuré n’est plus disponible. Mettez à jour GEMINI_EMBEDDING_MODEL.', 503);
        }
        if (429 === $status) {
            throw new DomainException('Gemini a saturé le quota. Réessayez dans une minute.', 503);
        }
        throw $last ?? new \RuntimeException('Gemini request failed.');
    }

    private function assertConfigured(): void
    {
        if ('' === trim($this->apiKey)) {
            throw new DomainException('La clé API Gemini n’est pas configurée.', 503);
        }
    }
}
