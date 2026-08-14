<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Feature\Port;

enum OidcIdentityValidationStatus: string
{
    case Valid = 'valid';
    case Invalid = 'invalid';
}
