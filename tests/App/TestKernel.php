<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Tests\App;

use BetterAuth\Symfony\BetterAuthBundle;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\Bundle\FixturesBundle\DoctrineFixturesBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\MonologBundle\MonologBundle;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;

/**
 * Minimal Symfony kernel for functional integration tests.
 *
 * Uses App\Entity namespace to be compatible with EntityAutoConfigurationPass.
 * Uses BETTER_AUTH_TEST_DATABASE_URL when set, otherwise defaults to SQLite.
 */
class TestKernel extends Kernel
{
    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new TwigBundle(),
            new SecurityBundle(),
            new DoctrineBundle(),
            new DoctrineFixturesBundle(),
            new MonologBundle(),
            new BetterAuthBundle(),
        ];
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        // Set FRONTEND_URL env var required by OAuthController, MagicLinkController, PasswordResetController
        if (!isset($_ENV['FRONTEND_URL'])) {
            $_ENV['FRONTEND_URL'] = 'http://localhost:5173';
            putenv('FRONTEND_URL=http://localhost:5173');
        }

        $loader->load(function (ContainerBuilder $container) {
            $container->loadFromExtension('framework', [
                'secret' => 'test-secret-for-functional-tests',
                'test' => true,
                'cache' => [
                    'app' => 'cache.adapter.array',
                ],
                'router' => [
                    'utf8' => true,
                    'resource' => __DIR__ . '/config/routes.yaml',
                ],
                'http_method_override' => false,
                'handle_all_throwables' => true,
                'php_errors' => ['log' => true],
                'session' => [
                    'handler_id' => null,
                    'storage_factory_id' => 'session.storage.factory.mock_file',
                ],
                'mailer' => ['dsn' => 'null://null'],
                'validation' => ['email_validation_mode' => 'html5'],
                'serializer' => ['enabled' => true],
            ]);

            $container->loadFromExtension('doctrine', [
                'dbal' => $this->buildDbalConfig(),
                'orm' => [
                    'naming_strategy' => 'doctrine.orm.naming_strategy.underscore_number_aware',
                    'auto_mapping' => false,
                    'mappings' => [
                        'App' => [
                            'is_bundle' => false,
                            'type' => 'attribute',
                            'dir' => dirname(__DIR__) . '/App/src/Entity',
                            'prefix' => 'App\Entity',
                            'alias' => 'App',
                        ],
                        'BetterAuthCoreEntities' => [
                            'is_bundle' => false,
                            'type' => 'attribute',
                            'dir' => dirname((new \ReflectionClass(\BetterAuth\Core\Entities\User::class))->getFileName()),
                            'prefix' => 'BetterAuth\Core\Entities',
                            'alias' => 'BetterAuthCoreEntities',
                        ],
                    ],
                ],
            ]);

            $container->loadFromExtension('security', [
                'password_hashers' => [
                    'Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface' => 'auto',
                ],
                'providers' => [
                    'better_auth_provider' => [
                        'id' => 'BetterAuth\Symfony\Security\BetterAuthUserProvider',
                    ],
                ],
                'firewalls' => [
                    'dev' => [
                        'pattern' => '^/(_(profiler|wdt)|css|images|js)/',
                        'security' => false,
                    ],
                    'main' => [
                        'lazy' => true,
                        'stateless' => true,
                        'provider' => 'better_auth_provider',
                        'custom_authenticators' => ['BetterAuth\Symfony\Security\BetterAuthAuthenticator'],
                    ],
                ],
                'access_control' => [],
            ]);

            $container->loadFromExtension('better_auth', [
                'mode' => 'api',
                'secret' => 'test-secret-key-for-functional-tests-at-least-32-chars!!',
                'openapi' => [
                    'enabled' => false,
                ],
                'security' => [
                    'auto_configure' => false,
                ],
                'cors' => [
                    'auto_configure' => false,
                ],
                'routing' => [
                    'auto_configure' => false,
                ],
            ]);

            // Explicitly configure repository entity classes for test entities
            $container->register('BetterAuth\Symfony\Storage\Doctrine\DoctrineEmailVerificationRepository')
                ->setAutowired(true)
                ->setArgument('$tokenClass', 'App\Entity\EmailVerificationToken');
            $container->register('BetterAuth\Symfony\Storage\Doctrine\DoctrineMagicLinkRepository')
                ->setAutowired(true)
                ->setArgument('$tokenClass', 'App\Entity\MagicLinkToken');
            $container->register('BetterAuth\Symfony\Storage\Doctrine\DoctrinePasswordResetRepository')
                ->setAutowired(true)
                ->setArgument('$tokenClass', 'App\Entity\PasswordResetToken');

            $container->loadFromExtension('monolog', [
                'handlers' => [
                    'main' => ['type' => 'null'],
                ],
            ]);
        });
    }

    public function getProjectDir(): string
    {
        return dirname(__DIR__, 2);
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/better_auth_test_kernel/' . $this->environment;
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/better_auth_test_logs';
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDbalConfig(): array
    {
        $url = $this->getTestDatabaseUrl();
        if ($url !== null) {
            return ['url' => $url];
        }

        return [
            'driver' => 'pdo_sqlite',
            'path' => $this->getCacheDir() . '/test.db',
        ];
    }

    private function getTestDatabaseUrl(): ?string
    {
        $value = $_ENV['BETTER_AUTH_TEST_DATABASE_URL']
            ?? $_SERVER['BETTER_AUTH_TEST_DATABASE_URL']
            ?? getenv('BETTER_AUTH_TEST_DATABASE_URL');

        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);
        return $value === '' ? null : $value;
    }
}
