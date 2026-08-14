<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Feature;

final readonly class AuthorizationTransaction
{
    public function __construct(
        public string $purpose,
        public string $provider,
        public string $clientIdentifier,
        public string $redirectUri,
        public string $state,
        public string $nonce,
        public string $codeVerifier,
        public \DateTimeImmutable $expiresAt,
    ) {
    }
}
