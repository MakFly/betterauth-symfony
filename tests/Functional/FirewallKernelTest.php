<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Tests\Functional;

use BetterAuth\Symfony\BetterAuthBundle;
use BetterAuth\Symfony\Security\BetterAuthAuthenticator;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;

final class FirewallKernelTest extends TestCase
{
    public function testTheBundleBuildsARealFirewallContainer(): void
    {
        $kernel = new FirewallKernel('test', true);
        $kernel->boot();

        self::assertTrue($kernel->getContainer()->has('better_auth.authenticator.api'));
        self::assertInstanceOf(BetterAuthAuthenticator::class, $kernel->getContainer()->get('better_auth.authenticator.api'));

        $kernel->shutdown();
    }
}

final class FirewallKernel extends Kernel
{
    public function registerBundles(): iterable
    {
        return [new FrameworkBundle(), new SecurityBundle(), new BetterAuthBundle()];
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(function (ContainerBuilder $container): void {
            $container->register(FirewallTestUserProvider::class, FirewallTestUserProvider::class)->setPublic(true);
            $container->loadFromExtension('framework', [
                'secret' => 'test-secret',
                'test' => true,
                'http_method_override' => false,
                'handle_all_throwables' => true,
                'php_errors' => ['log' => true],
                'router' => ['utf8' => true, 'resource' => __DIR__.'/routes.yaml', 'type' => 'yaml'],
            ]);
            $container->loadFromExtension('security', [
                'providers' => ['app_users' => ['id' => FirewallTestUserProvider::class]],
                'firewalls' => ['api' => ['pattern' => '^/', 'stateless' => true, 'provider' => 'app_users', 'better_auth' => null]],
            ]);
            $container->loadFromExtension('better_auth', [
                'secret' => str_repeat('a', 32),
                'refresh_token' => ['enabled' => false],
            ]);
        });
    }

    public function getCacheDir(): string
    {
        return $this->temporaryDirectory('cache');
    }

    public function getLogDir(): string
    {
        return $this->temporaryDirectory('log');
    }

    private function temporaryDirectory(string $purpose): string
    {
        return sprintf('%s/betterauth-symfony-firewall-%s-%s-%s', sys_get_temp_dir(), $purpose, str_replace('.', '-', Kernel::VERSION), md5(__DIR__));
    }
}

/** @implements UserProviderInterface<FirewallTestUser> */
final class FirewallTestUserProvider implements UserProviderInterface
{
    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        return new FirewallTestUser($identifier);
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof FirewallTestUser) {
            throw new UnsupportedUserException();
        }

        return $user;
    }

    public function supportsClass(string $class): bool
    {
        return $class === FirewallTestUser::class;
    }
}

final class FirewallTestUser implements UserInterface
{
    public function __construct(private readonly string $identifier)
    {
    }

    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function eraseCredentials(): void
    {
    }

    public function getUserIdentifier(): string
    {
        return $this->identifier === '' ? 'unknown' : $this->identifier;
    }
}
