<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Tests\Installer;

use PHPUnit\Framework\TestCase;

/**
 * Test BundleRegistrar auto-registration logic
 */
class BundleRegistrarTest extends TestCase
{
    public function testBundleRegistrarExists(): void
    {
        $this->assertTrue(
            class_exists('BetterAuth\Symfony\Installer\BundleRegistrar'),
            'BundleRegistrar class should exist'
        );
    }

    public function testBundleRegistrarHasRegisterMethod(): void
    {
        $this->assertTrue(
            method_exists('BetterAuth\Symfony\Installer\BundleRegistrar', 'register'),
            'BundleRegistrar should have a register() method'
        );
    }
}
