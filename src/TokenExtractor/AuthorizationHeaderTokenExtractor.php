<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\TokenExtractor;

use Symfony\Component\HttpFoundation\Request;

/**
 * Extracts token from Authorization header.
 * Similar to Lexik's AuthorizationHeaderTokenExtractor.
 *
 * Supports configurable prefix (default: "Bearer") and header name (default: "Authorization").
 *
 * Example:
 *   Authorization: Bearer v4.local.xxxxx
 */
final class AuthorizationHeaderTokenExtractor implements TokenExtractorInterface
{
    public function __construct(
        private readonly string $prefix = 'Bearer',
        private readonly string $name = 'Authorization',
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function extract(Request $request): ?string
    {
        $header = $request->headers->get($this->name);

        if ($header === null) {
            return null;
        }

        $prefix = $this->prefix . ' ';

        // Case-insensitive comparison for Bearer scheme (RFC 7235)
        if (strncasecmp($header, $prefix, strlen($prefix)) !== 0) {
            return null;
        }

        return substr($header, strlen($prefix));
    }
}

