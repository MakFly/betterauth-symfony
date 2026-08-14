<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Feature\Port;

interface OAuthClientPortInterface
{
    /** Returns true only for an application-registered provider and redirect URI. */
    public function allows(string $provider, string $redirectUri): bool;

    /**
     * Build an authorization-code URL using the supplied S256 code challenge
     * and the explicit `code_challenge_method=S256` parameter.
     */
    public function authorizationUrl(string $provider, string $redirectUri, string $state, string $codeChallenge): string;

    /** @return array<string, mixed> */
    public function exchangeAuthorizationCode(string $provider, string $code, string $redirectUri, string $codeVerifier): array;
}
