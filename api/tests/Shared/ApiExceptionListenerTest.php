<?php

namespace App\Tests\Shared;

use App\Shared\ApiExceptionListener;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class ApiExceptionListenerTest extends TestCase
{
    public function testTypeErrorBecomesJsonInternalError(): void
    {
        $listener = new ApiExceptionListener($this->createMock(LoggerInterface::class));
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/api/books/x/questions', 'POST');
        $request->headers->set('Accept', 'application/json');
        $event = new ExceptionEvent(
            $kernel,
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            new \TypeError('GeminiClient argument must be of type int, string given'),
        );

        $listener($event);

        $response = $event->getResponse();
        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(500, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('INTERNAL_ERROR', $payload['error']['code'] ?? null);
        self::assertSame('Une erreur interne est survenue.', $payload['error']['message'] ?? null);
        self::assertIsString($payload['error']['correlationId'] ?? null);
        self::assertNotSame('', $payload['error']['correlationId']);
    }
}
