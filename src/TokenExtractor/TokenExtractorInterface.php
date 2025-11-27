<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\TokenExtractor;

use Symfony\Component\HttpFoundation\Request;

/**
 * Interface for token extractors.
 * Similar to Lexik's TokenExtractorInterface.
 *
 * Extractors are used to retrieve tokens from HTTP requests.
 * Multiple extractors can be chained using ChainTokenExtractor.
 */
interface TokenExtractorInterface
{
    /**
     * Extract a token from the request.
     *
     * @return string|null The token if found, null otherwise
     */
    public function extract(Request $request): ?string;
}

