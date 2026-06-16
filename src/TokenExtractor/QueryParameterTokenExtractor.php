<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\TokenExtractor;

use Symfony\Component\HttpFoundation\Request;

/**
 * Extracts token from query parameter.
 * Similar to Lexik's QueryParameterTokenExtractor.
 *
 * SECURITY WARNING: Using tokens in URLs is strongly discouraged. The token leaks into
 * server access logs, the browser history and the `Referer` header sent to third parties.
 * This extractor is therefore NOT part of the default ChainTokenExtractor (which only reads
 * the Authorization header and cookies). Wire it explicitly only for legacy interoperability,
 * and prefer short-lived, single-use tokens when you do.
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

