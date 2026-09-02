<?php

namespace App\Shared;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

#[AsEventListener(event: KernelEvents::EXCEPTION, priority: 10)]
final readonly class ApiExceptionListener
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        if (!str_starts_with($request->getPathInfo(), '/api')) {
            return;
        }

        $exception = $event->getThrowable();
        $correlationId = $request->headers->get('X-Correlation-Id')
            ?: $request->headers->get('X-Request-Id')
            ?: bin2hex(random_bytes(8));

        [$status, $code, $message] = $this->map($exception);
        if ($status >= 500) {
            $this->logger->error($exception->getMessage(), [
                'exception' => $exception,
                'correlationId' => $correlationId,
                'path' => $request->getPathInfo(),
            ]);
        }

        $response = new JsonResponse([
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => null,
                'correlationId' => $correlationId,
            ],
        ], $status);
        $response->headers->set('X-Correlation-Id', $correlationId);
        $event->setResponse($response);
    }

    /**
     * @return array{0: int, 1: string, 2: string}
     */
    private function map(\Throwable $exception): array
    {
        if ($exception instanceof DomainException) {
            return [$exception->statusCode, 'DOMAIN_ERROR', $exception->getMessage()];
        }
        if ($exception instanceof AuthenticationException) {
            return [401, 'AUTH_REQUIRED', 'Authentification requise.'];
        }
        if ($exception instanceof AccessDeniedException) {
            return [403, 'FORBIDDEN', 'Accès refusé.'];
        }
        if ($exception instanceof HttpExceptionInterface) {
            return [$exception->getStatusCode(), 'HTTP_ERROR', $exception->getMessage() ?: 'Requête invalide.'];
        }

        return [500, 'INTERNAL_ERROR', 'Une erreur interne est survenue.'];
    }
}
