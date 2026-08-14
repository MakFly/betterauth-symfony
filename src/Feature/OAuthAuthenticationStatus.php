<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Feature;

enum OAuthAuthenticationStatus: string
{
    case Completed = 'completed';
    case Invalid = 'invalid';
}
