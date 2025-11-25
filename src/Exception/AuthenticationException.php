<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Exception thrown when authentication fails.
 *
 * This exception is automatically converted to a 401 JSON response
 * by the AuthExceptionListener.
 */
class AuthenticationException extends HttpException
{
    public function __construct(
        string $message = 'Authentication required',
        int $statusCode = 401,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($statusCode, $message, $previous);
    }
}
