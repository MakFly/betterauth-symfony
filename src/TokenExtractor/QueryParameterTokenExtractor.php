<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\TokenExtractor;

use Symfony\Component\HttpFoundation\Request;

/**
 * Extracts token from query parameter.
 * Similar to Lexik's QueryParameterTokenExtractor.
 *
 * WARNING: Using tokens in URLs is generally not recommended for security reasons
 * (tokens may be logged in server access logs, browser history, etc.).
 * Use this extractor only when absolutely necessary (e.g., for legacy systems).
 *
 * Example:
 *   GET /api/resource?bearer=v4.local.xxxxx
 */
final class QueryParameterTokenExtractor implements TokenExtractorInterface
{
    public function __construct(
        private readonly string $name = 'bearer',
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function extract(Request $request): ?string
    {
        return $request->query->get($this->name);
    }
}

