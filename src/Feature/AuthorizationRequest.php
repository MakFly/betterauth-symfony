<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Feature;

final readonly class AuthorizationRequest
{
    public function __construct(public string $authorizationUrl, public string $state)
    {
    }
}
