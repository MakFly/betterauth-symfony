<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Event;

/**
 * BetterAuth Events - Compatible with Lexik JWT patterns.
 *
 * This provides 8 events matching Lexik JWT for easy migration:
 * - TOKEN_CREATED: Modify payload before signing (like JWTCreatedEvent)
 * - TOKEN_DECODED: Validate after decoding (like JWTDecodedEvent)
 * - TOKEN_AUTHENTICATED: After full authentication (like JWTAuthenticatedEvent)
 * - TOKEN_INVALID: When token is invalid (like JWTInvalidEvent)
 * - TOKEN_NOT_FOUND: When no token in request (like JWTNotFoundEvent)
 * - TOKEN_EXPIRED: When token has expired (like JWTExpiredEvent)
 * - AUTHENTICATION_SUCCESS: After successful authentication
 * - AUTHENTICATION_FAILURE: After failed authentication
 */
final class BetterAuthEvents
{
    /**
     * Dispatched before the Paseto token is signed.
     * Allows adding custom claims to the payload.
     * Similar to Lexik's JWTCreatedEvent.
     *
     * @Event("BetterAuth\Symfony\Event\TokenCreatedEvent")
     */
    public const TOKEN_CREATED = 'better_auth.token_created';

    /**
     * Dispatched after the token is decoded but before validation.
     * Allows custom validation of the decoded payload.
     * Similar to Lexik's JWTDecodedEvent.
     *
     * @Event("BetterAuth\Symfony\Event\TokenDecodedEvent")
     */
    public const TOKEN_DECODED = 'better_auth.token_decoded';

    /**
     * Dispatched after the token is fully validated and user authenticated.
     * Similar to Lexik's JWTAuthenticatedEvent.
     *
     * @Event("BetterAuth\Symfony\Event\TokenAuthenticatedEvent")
     */
    public const TOKEN_AUTHENTICATED = 'better_auth.token_authenticated';

    /**
     * Dispatched when a token is invalid (bad signature, tampered, etc).
     * Similar to Lexik's JWTInvalidEvent.
     *
     * @Event("BetterAuth\Symfony\Event\TokenInvalidEvent")
     */
    public const TOKEN_INVALID = 'better_auth.token_invalid';

    /**
     * Dispatched when no token is found in the request.
     * Similar to Lexik's JWTNotFoundEvent.
     *
     * @Event("BetterAuth\Symfony\Event\TokenNotFoundEvent")
     */
    public const TOKEN_NOT_FOUND = 'better_auth.token_not_found';

    /**
     * Dispatched when a token has expired.
     * Similar to Lexik's JWTExpiredEvent.
     *
     * @Event("BetterAuth\Symfony\Event\TokenExpiredEvent")
     */
    public const TOKEN_EXPIRED = 'better_auth.token_expired';

    /**
     * Dispatched when authentication fails (invalid token).
     * Allows customizing the error response.
     *
     * @Event("BetterAuth\Symfony\Event\AuthenticationFailureEvent")
     */
    public const AUTHENTICATION_FAILURE = 'better_auth.authentication_failure';

    /**
     * Dispatched when a token is successfully verified on a protected route.
     *
     * @Event("BetterAuth\Symfony\Event\AuthenticationSuccessEvent")
     */
    public const AUTHENTICATION_SUCCESS = 'better_auth.authentication_success';
}
