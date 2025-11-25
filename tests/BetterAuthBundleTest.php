<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Tests;

use BetterAuth\Symfony\BetterAuthBundle;
use BetterAuth\Symfony\DependencyInjection\BetterAuthExtension;
use BetterAuth\Symfony\DependencyInjection\BetterAuthSecurityPass;
use BetterAuth\Symfony\DependencyInjection\EntityAutoConfigurationPass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Test BetterAuthBundle configuration and initialization
 */
class BetterAuthBundleTest extends TestCase
{
    private BetterAuthBundle $bundle;

    protected function setUp(): void
    {
        $this->bundle = new BetterAuthBundle();
    }

    public function testBundleHasCorrectExtension(): void
    {
        $extension = $this->bundle->getContainerExtension();

        $this->assertInstanceOf(BetterAuthExtension::class, $extension);
    }

    public function testBundleRegistersCompilerPasses(): void
    {
        $container = new ContainerBuilder();

        $this->bundle->build($container);

        $passes = $container->getCompilerPassConfig()->getPasses();

        $hasSecurityPass = false;
        $hasEntityAutoConfigPass = false;

        foreach ($passes as $pass) {
            if ($pass instanceof BetterAuthSecurityPass) {
                $hasSecurityPass = true;
            }
            if ($pass instanceof EntityAutoConfigurationPass) {
                $hasEntityAutoConfigPass = true;
            }
        }

        $this->assertTrue($hasSecurityPass, 'BetterAuthSecurityPass should be registered');
        $this->assertTrue($hasEntityAutoConfigPass, 'EntityAutoConfigurationPass should be registered');
    }
}
