<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Tests\DependencyInjection;

use BetterAuth\Symfony\DependencyInjection\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

/**
 * Test BetterAuth configuration validation and defaults
 */
class ConfigurationTest extends TestCase
{
    private Configuration $configuration;
    private Processor $processor;

    protected function setUp(): void
    {
        $this->configuration = new Configuration();
        $this->processor = new Processor();
    }

    public function testDefaultConfiguration(): void
    {
        $config = $this->processor->processConfiguration($this->configuration, []);

        $this->assertSame('hybrid', $config['mode']);
        $this->assertSame('change_me_in_production', $config['secret']);
        $this->assertSame(604800, $config['session']['lifetime']);
        $this->assertSame('better_auth_session', $config['session']['cookie_name']);
        $this->assertSame(3600, $config['token']['lifetime']);
        $this->assertSame(2592000, $config['token']['refresh_lifetime']);
        $this->assertTrue($config['multi_tenant']['enabled']);
        $this->assertSame('member', $config['multi_tenant']['default_role']);
    }

    public function testApiModeConfiguration(): void
    {
        $config = $this->processor->processConfiguration($this->configuration, [
            'better_auth' => [
                'mode' => 'api',
                'secret' => 'my_secret_key',
            ],
        ]);

        $this->assertSame('api', $config['mode']);
        $this->assertSame('my_secret_key', $config['secret']);
    }

    public function testInvalidModeThrowsException(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('/Invalid mode.*Must be "session", "api", or "hybrid"/');

        $this->processor->processConfiguration($this->configuration, [
            'better_auth' => [
                'mode' => 'invalid',
            ],
        ]);
    }

    public function testCustomTokenLifetime(): void
    {
        $config = $this->processor->processConfiguration($this->configuration, [
            'better_auth' => [
                'token' => [
                    'lifetime' => 7200,
                    'refresh_lifetime' => 86400,
                ],
            ],
        ]);

        $this->assertSame(7200, $config['token']['lifetime']);
        $this->assertSame(86400, $config['token']['refresh_lifetime']);
    }

    public function testOAuthProvidersConfiguration(): void
    {
        $config = $this->processor->processConfiguration($this->configuration, [
            'better_auth' => [
                'oauth' => [
                    'providers' => [
                        'google' => [
                            'enabled' => true,
                            'client_id' => 'google_client_id',
                            'client_secret' => 'google_secret',
                            'redirect_uri' => 'http://localhost/auth/google/callback',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($config['oauth']['providers']['google']['enabled']);
        $this->assertSame('google_client_id', $config['oauth']['providers']['google']['client_id']);
        $this->assertSame('google_secret', $config['oauth']['providers']['google']['client_secret']);
    }

    public function testMultiTenantConfiguration(): void
    {
        $config = $this->processor->processConfiguration($this->configuration, [
            'better_auth' => [
                'multi_tenant' => [
                    'enabled' => false,
                    'default_role' => 'user',
                ],
            ],
        ]);

        $this->assertFalse($config['multi_tenant']['enabled']);
        $this->assertSame('user', $config['multi_tenant']['default_role']);
    }
}
