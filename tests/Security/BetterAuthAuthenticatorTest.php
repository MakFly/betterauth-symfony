<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Tests\Security;

use BetterAuth\Symfony\Core\TokenService;
use BetterAuth\Symfony\Security\BetterAuthAuthenticator;
use BetterAuth\Symfony\TokenExtractor\AuthorizationHeaderTokenExtractor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

final class BetterAuthAuthenticatorTest extends TestCase
{
    public function testExpiredInvalidAndNonAccessTokensReturn401(): void
    {
        $tokens = new TokenService(str_repeat('a', 32));
        $authenticator = new BetterAuthAuthenticator($tokens, new AuthorizationHeaderTokenExtractor(), new TestUserProvider());

        foreach ([
            $tokens->createAccessToken('user-42', [], -1),
            'not-a-paseto',
            $tokens->sign(['sub' => 'user-42', 'type' => 'refresh'], 60),
        ] as $token) {
            $request = Request::create('/private', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);
            try {
                $authenticator->authenticate($request);
                self::fail('The token must not authenticate.');
            } catch (CustomUserMessageAuthenticationException $exception) {
                self::assertSame(401, $authenticator->onAuthenticationFailure($request, $exception)->getStatusCode());
            }
        }
    }

    public function testCustomUserIdClaimIsEmittedAndRead(): void
    {
        $tokens = new TokenService(str_repeat('a', 32), userIdClaim: 'uid');
        $authenticator = new BetterAuthAuthenticator($tokens, new AuthorizationHeaderTokenExtractor(), new TestUserProvider(), 'uid');
        $token = $tokens->createAccessToken('user-42', [], 60);

        $claims = $tokens->parseAccessToken($token);
        self::assertSame('user-42', $claims['uid']);
        $authenticator->authenticate(Request::create('/private', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token]));
    }
}

/** @implements UserProviderInterface<TestUser> */
final class TestUserProvider implements UserProviderInterface
{
    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        return new TestUser($identifier);
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof TestUser) {
            throw new UnsupportedUserException();
        }

        return $user;
    }

    public function supportsClass(string $class): bool
    {
        return $class === TestUser::class;
    }
}

final class TestUser implements UserInterface
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
