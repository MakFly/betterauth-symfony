<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Token;

final readonly class TokenPair
{
    public function __construct(
        public string $accessToken,
        public ?string $refreshToken,
        public int $expiresIn,
    ) {
    }
}
