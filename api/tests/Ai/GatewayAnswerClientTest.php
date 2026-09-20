<?php

namespace App\Tests\Ai;

use App\Ai\GatewayAnswerClient;
use App\Shared\DomainException;
use AiGateway\Sdk\CallableTransport;
use AiGateway\Sdk\GatewayClient;
use PHPUnit\Framework\TestCase;

final class GatewayAnswerClientTest extends TestCase
{
    public function testAnswerUsesGatewayJsonContract(): void
    {
        $client = new GatewayAnswerClient(
            new GatewayClient(new CallableTransport(static function (string $method, string $path, array $headers, array $body): array {
                self::assertSame('POST', $method);
                self::assertSame('/v1/generate', $path);
                self::assertSame('APP_SSK_BOOK', $body['application']);
                self::assertSame('low', $body['complexity']);
                self::assertArrayHasKey('jsonSchema', $body);
                self::assertStringContainsString('graphie', (string) $body['prompt']);

                return [
                    'status' => 200,
                    'body' => [
                        'text' => '{"answer":"Le texte le dit.","sourceIds":["src-1"],"insufficientEvidence":false}',
                        'provider' => 'ollama',
                        'model' => 'llama3.2:3b',
                        'correlationId' => 'corr-ssk-1',
                        'json' => [
                            'answer' => 'Le texte le dit.',
                            'sourceIds' => ['src-1'],
                            'insufficientEvidence' => false,
                        ],
                    ],
                    'correlationId' => 'corr-ssk-1',
                    'error' => null,
                ];
            }), 'test-gateway-key'),
            'APP_SSK_BOOK',
            'low',
        );

        $out = $client->answer('Qui est-ce ?', [[
            'id' => 'src-1',
            'start_page' => 9,
            'end_page' => 10,
            'content' => 'Un passage.',
        ]]);

        self::assertSame('Le texte le dit.', $out['answer']);
        self::assertSame(['src-1'], $out['sourceIds']);
        self::assertFalse($out['insufficientEvidence']);
    }

    public function testUnavailableDoesNotInventAnswer(): void
    {
        $client = new GatewayAnswerClient(
            new GatewayClient(new CallableTransport(static function (): array {
                return [
                    'status' => 503,
                    'body' => [
                        'error' => [
                            'code' => 'AIGW.INFERENCE.UNAVAILABLE',
                            'message' => 'Les deux voies de la route sont indisponibles.',
                            'details' => null,
                            'correlationId' => 'corr-down',
                        ],
                    ],
                    'correlationId' => 'corr-down',
                    'error' => null,
                ];
            }), 'test-gateway-key'),
            'APP_SSK_BOOK',
            'low',
        );

        try {
            $client->answer('Qui est-ce ?', [[
                'id' => 'src-1',
                'start_page' => 1,
                'end_page' => 1,
                'content' => 'x',
            ]]);
            self::fail('expected DomainException');
        } catch (DomainException $e) {
            self::assertSame(503, $e->statusCode);
            self::assertStringNotContainsString('Le texte le dit', $e->getMessage());
        }
    }

    public function testInvalidJsonDoesNotInventAnswer(): void
    {
        $client = new GatewayAnswerClient(
            new GatewayClient(new CallableTransport(static function (): array {
                return [
                    'status' => 200,
                    'body' => [
                        'text' => 'ceci n’est pas un JSON',
                        'provider' => 'ollama',
                        'model' => 'llama3.2:3b',
                        'correlationId' => 'corr-bad-json',
                    ],
                    'correlationId' => 'corr-bad-json',
                    'error' => null,
                ];
            }), 'test-gateway-key'),
            'APP_SSK_BOOK',
            'low',
        );

        try {
            $client->answer('Qui est-ce ?', [[
                'id' => 'src-1',
                'start_page' => 1,
                'end_page' => 1,
                'content' => 'x',
            ]]);
            self::fail('expected DomainException');
        } catch (DomainException $e) {
            self::assertSame(502, $e->statusCode);
            self::assertStringNotContainsString('ceci n’est pas un JSON', $e->getMessage());
        }
    }

    public function testPlaceholderAnswerDoesNotInventBookFact(): void
    {
        $client = new GatewayAnswerClient(
            new GatewayClient(new CallableTransport(static function (): array {
                return [
                    'status' => 200,
                    'body' => [
                        'text' => '{"answer":"nous n\'avons pas reçu d\'answer","sourceIds":[],"insufficientEvidence":true}',
                        'provider' => 'ollama',
                        'model' => 'llama3.2:3b',
                        'correlationId' => 'corr-placeholder',
                        'json' => [
                            'answer' => 'nous n\'avons pas reçu d\'answer',
                            'sourceIds' => [],
                            'insufficientEvidence' => true,
                        ],
                    ],
                    'correlationId' => 'corr-placeholder',
                    'error' => null,
                ];
            }), 'test-gateway-key'),
            'APP_SSK_BOOK',
            'low',
        );

        try {
            $client->answer('Qui est-ce ?', [[
                'id' => 'src-1',
                'start_page' => 1,
                'end_page' => 1,
                'content' => 'x',
            ]]);
            self::fail('expected DomainException');
        } catch (DomainException $e) {
            self::assertSame(502, $e->statusCode);
            self::assertStringNotContainsString('n\'avons pas reçu', $e->getMessage());
        }
    }
}
