<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Tests\Fixtures;

use BetterAuth\Symfony\Model\RefreshToken;

/**
 * Concrete RefreshToken implementation for tests.
 * Extends the abstract Symfony model superclass without ORM mapping.
 */
class TestRefreshToken extends RefreshToken
{
}
