<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Exception thrown when request validation fails.
 *
 * This exception is automatically converted to a 400 JSON response
 * by the AuthExceptionListener, including detailed validation errors.
 */
class ValidationException extends HttpException
{
    /**
     * @param array<string, string[]> $errors Field-level validation errors
     */
    public function __construct(
        string $message = 'Validation failed',
        private readonly array $errors = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct(400, $message, $previous);
    }

    /**
     * @return array<string, string[]>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
