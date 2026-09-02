<?php

namespace App\Ai;

use App\Shared\DomainException;
use AiGateway\Sdk\GatewayClient;
use AiGateway\Sdk\GatewayClientException;
use AiGateway\Sdk\TransportException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * PEP Ask-the-book : generate via ai-gateway, pas Gemini direct.
 *
 * Pourquoi : AIGW-001 / P9 ssk-book. Les embeddings 768 restent GeminiClient.
 * Garantit : timeout / 503 → DomainException, jamais un texte inventé.
 * Ne fait pas : ré-indexer le livre ; choisir Ollama vs Gemini (c’est SQL côté gateway).
 */
final readonly class GatewayAnswerClient
{
    /** @var array<string, mixed> */
    private const JSON_SCHEMA = [
        'type' => 'object',
        'properties' => [
            'answer' => ['type' => 'string'],
            'sourceIds' => ['type' => 'array', 'items' => ['type' => 'string']],
            'insufficientEvidence' => ['type' => 'boolean'],
        ],
        'required' => ['answer', 'sourceIds', 'insufficientEvidence'],
    ];

    public function __construct(
        private GatewayClient $gateway,
        #[Autowire('%env(AIGW_APPLICATION)%')] private string $application,
        #[Autowire('%env(AIGW_COMPLEXITY)%')] private string $complexity,
    ) {
    }

    /**
     * @param list<array{id: string, start_page: int, end_page: int, content: string}> $sources
     * @return array{answer: string, sourceIds: list<string>, insufficientEvidence: bool}
     */
    public function answer(string $question, array $sources): array
    {
        $context = implode("\n\n", array_map(
            static fn (array $s): string => sprintf('[SOURCE %s | pages %d-%d]\n%s', $s['id'], $s['start_page'], $s['end_page'], $s['content']),
            $sources,
        ));
        $prompt = <<<PROMPT
Tu réponds uniquement à partir des sources du livre ci-dessous. N'invente rien.
Si les sources sont insuffisantes, réponds exactement que le livre ne permet pas de répondre.
Respecte les nuances du texte et distingue les affirmations de l'auteur.
Retourne uniquement un JSON valide avec: answer (string), sourceIds (array de chaînes), insufficientEvidence (boolean).

QUESTION:
{$question}

SOURCES:
{$context}
PROMPT;

        try {
            $out = $this->gateway->generate(
                $this->application,
                'generate',
                $this->complexity,
                $prompt,
                self::JSON_SCHEMA,
            );
        } catch (TransportException) {
            throw new DomainException('Le service d’inférence n’a pas répondu. Réessayez dans un instant.', 503);
        } catch (GatewayClientException $e) {
            if ($e->errorCode === 'AIGW.INFERENCE.UNAVAILABLE') {
                throw new DomainException('L’inférence est indisponible (voies locale et cloud). Réessayez plus tard.', 503);
            }
            throw new DomainException('La génération de la réponse a échoué.', $e->httpStatus >= 400 && $e->httpStatus < 600 ? $e->httpStatus : 503);
        }

        $payload = $out->json ?? json_decode($out->text, true);
        if (!is_array($payload)
            || !isset($payload['answer'], $payload['sourceIds'], $payload['insufficientEvidence'])
            || !is_string($payload['answer'])
            || !is_array($payload['sourceIds'])
            || !is_bool($payload['insufficientEvidence'])
        ) {
            throw new DomainException('Le gateway a renvoyé un contrat de réponse invalide.', 502);
        }

        /** @var list<string> $ids */
        $ids = array_values(array_filter($payload['sourceIds'], static fn ($id): bool => is_string($id)));

        return [
            'answer' => $payload['answer'],
            'sourceIds' => $ids,
            'insufficientEvidence' => $payload['insufficientEvidence'],
        ];
    }
}
