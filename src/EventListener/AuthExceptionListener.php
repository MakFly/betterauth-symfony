<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\EventListener;

use BetterAuth\Symfony\Exception\AuthenticationException;
use BetterAuth\Symfony\Exception\ValidationException;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Converts authentication and validation exceptions to JSON responses.
 *
 * This listener handles exceptions thrown during controller execution
 * and converts them to appropriate JSON error responses.
 *
 * Handled exceptions:
 * - AuthenticationException -> 401 Unauthorized
 * - ValidationException -> 400 Bad Request with errors array
 * - HttpExceptionInterface -> Uses exception's status code
 */
#[AsEventListener(event: 'kernel.exception', priority: 10)]
class AuthExceptionListener
{
    public function __construct(
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        // Only handle auth-related exceptions
        if (!$this->shouldHandle($exception)) {
            return;
        }

        $this->logException($exception);

        [$statusCode, $data] = $this->mapExceptionToResponse($exception);

        $event->setResponse(new JsonResponse($data, $statusCode));
    }

    private function shouldHandle(\Throwable $exception): bool
    {
        return $exception instanceof AuthenticationException
            || $exception instanceof ValidationException
            || $exception instanceof HttpExceptionInterface;
    }

    /**
     * @return array{0: int, 1: array<string, mixed>}
     */
    private function mapExceptionToResponse(\Throwable $exception): array
    {
        return match (true) {
            $exception instanceof AuthenticationException => [
                401,
                ['error' => $exception->getMessage()],
            ],
            $exception instanceof ValidationException => [
                400,
                [
                    'error' => $exception->getMessage(),
                    'errors' => $exception->getErrors(),
                ],
            ],
            $exception instanceof HttpExceptionInterface => [
                $exception->getStatusCode(),
                ['error' => $exception->getMessage()],
            ],
            default => [
                500,
                ['error' => 'Internal server error'],
            ],
        };
    }

    private function logException(\Throwable $exception): void
    {
        $context = [
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
        ];

        if ($exception instanceof AuthenticationException) {
            $this->logger?->warning('Authentication failed', $context);
        } elseif ($exception instanceof ValidationException) {
            $context['errors'] = $exception->getErrors();
            $this->logger?->info('Validation failed', $context);
        } else {
            $this->logger?->error('Request failed', $context);
        }
    }
}
