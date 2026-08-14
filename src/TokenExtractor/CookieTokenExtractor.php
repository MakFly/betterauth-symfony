<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\TokenExtractor;

use Symfony\Component\HttpFoundation\Request;

final readonly class CookieTokenExtractor implements TokenExtractorInterface
{
    public function __construct(private string $name = 'access_token', private int $maxLength = 8192)
    {
    }

    public function extract(Request $request): ?string
    {
        $token = $request->cookies->get($this->name);

        return is_string($token) && $token !== '' && strlen($token) <= $this->maxLength ? $token : null;
    }
}
