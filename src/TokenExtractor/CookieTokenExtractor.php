<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\TokenExtractor;

use Symfony\Component\HttpFoundation\Request;

/**
 * Extracts token from a cookie.
 * Similar to Lexik's CookieTokenExtractor.
 *
 * Useful for session-like token storage in web applications.
 *
 * Example:
 *   Cookie: access_token=v4.local.xxxxx
 */
final class CookieTokenExtractor implements TokenExtractorInterface
{
    public function __construct(
        private readonly string $name = 'access_token',
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function extract(Request $request): ?string
    {
        return $request->cookies->get($this->name);
    }
}

