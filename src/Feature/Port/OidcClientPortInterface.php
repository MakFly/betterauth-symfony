<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Feature\Port;

interface OidcClientPortInterface
{
    public function allows(string $clientIdentifier, string $redirectUri): bool;

    public function authorizationUrl(string $issuer, string $clientIdentifier, string $redirectUri, string $state, string $nonce, string $codeChallenge): string;

    /**
     * The port MUST validate the provider ID-token signature plus expected
     * issuer, audience and nonce before returning a valid result. This bundle
     * never treats its PASETO access token as an OIDC ID token.
     */
    public function exchangeAndValidateAuthorizationCode(string $issuer, string $clientIdentifier, string $code, string $redirectUri, string $codeVerifier, string $expectedNonce): OidcIdentityValidationResult;
}
