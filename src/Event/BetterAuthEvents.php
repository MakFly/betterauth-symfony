<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Event;

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
     * Dispatched when authentication fails (invalid token).
     * Allows customizing the error response.
     *
     * @Event("BetterAuth\Symfony\Event\AuthenticationFailureEvent")
     */
    public const AUTHENTICATION_FAILURE = 'better_auth.authentication_failure';

    /**
     * Dispatched when a token is successfully verified on a protected route.
     */
    public const AUTHENTICATION_SUCCESS = 'better_auth.authentication_success';
}
