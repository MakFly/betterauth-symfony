<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Controller\Trait;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Trait for safe error responses that don't leak internal details.
 */
trait SafeErrorResponseTrait
{
    /**
     * Return a safe error response with a correlation ID for debugging.
     *
     * @param \Throwable $e The exception (logged, not exposed)
     * @param int $statusCode HTTP status code
     * @param string $publicMessage Message shown to the user
     * @param string $context Log context identifier
     */
    protected function safeError(
        \Throwable $e,
        int $statusCode = 500,
        string $publicMessage = 'An error occurred',
        string $context = 'unknown',
    ): JsonResponse {
        $correlationId = bin2hex(random_bytes(8));

        if (property_exists($this, 'logger') && $this->logger instanceof LoggerInterface) {
            $this->logger->error($publicMessage, [
                'correlation_id' => $correlationId,
                'context' => $context,
                'error' => $e->getMessage(),
                'exception_class' => $e::class,
            ]);
        }

        return new JsonResponse([
            'error' => $publicMessage,
            'correlation_id' => $correlationId,
        ], $statusCode);
    }
}
