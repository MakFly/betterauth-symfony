<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\TokenExtractor;

use Symfony\Component\HttpFoundation\Request;

final class AuthorizationHeaderTokenExtractor implements TokenExtractorInterface
{
    public function __construct(private readonly int $maxLength = 8192)
    {
    }

    public function extract(Request $request): ?string
    {
        $header = $request->headers->get('Authorization');
        if (!is_string($header) || strlen($header) > $this->maxLength || preg_match('/^Bearer ([^\\s]+)$/iD', $header, $matches) !== 1) {
            return null;
        }

        return strlen($matches[1]) <= $this->maxLength ? $matches[1] : null;
    }
}
