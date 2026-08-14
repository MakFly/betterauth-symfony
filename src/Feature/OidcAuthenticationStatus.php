<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Feature;

enum OidcAuthenticationStatus: string
{
    case Authenticated = 'authenticated';
    case Invalid = 'invalid';
}
