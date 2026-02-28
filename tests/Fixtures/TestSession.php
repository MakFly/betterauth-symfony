<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Tests\Fixtures;

use BetterAuth\Symfony\Model\Session;

/**
 * Concrete Session implementation for tests.
 * Extends the abstract Symfony model superclass without ORM mapping.
 */
class TestSession extends Session
{
}
