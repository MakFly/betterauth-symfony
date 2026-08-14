<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Token;

enum RefreshRotationStatus: string
{
    case Rotated = 'rotated';
    case Replayed = 'replayed';
    case Invalid = 'invalid';
}
