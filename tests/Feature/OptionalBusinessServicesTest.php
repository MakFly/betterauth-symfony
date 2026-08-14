<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Tests\Feature;

use BetterAuth\Symfony\Feature\AuthorizationTransaction;
use BetterAuth\Symfony\Feature\DeviceService;
use BetterAuth\Symfony\Feature\OAuthAuthenticationStatus;
use BetterAuth\Symfony\Feature\OAuthService;
use BetterAuth\Symfony\Feature\OidcAuthenticationStatus;
use BetterAuth\Symfony\Feature\OidcService;
use BetterAuth\Symfony\Feature\OneTimeTokenService;
use BetterAuth\Symfony\Feature\Port\AuthorizationTransactionStoreInterface;
use BetterAuth\Symfony\Feature\Port\DeviceStoreInterface;
use BetterAuth\Symfony\Feature\Port\OAuthClientPortInterface;
use BetterAuth\Symfony\Feature\Port\OidcClientPortInterface;
use BetterAuth\Symfony\Feature\Port\OidcIdentityValidationResult;
use BetterAuth\Symfony\Feature\Port\OneTimeTokenStoreInterface;
use BetterAuth\Symfony\Feature\Port\TenantMembershipStoreInterface;
use BetterAuth\Symfony\Feature\Port\TotpStoreInterface;
use BetterAuth\Symfony\Feature\TenantMembershipService;
use BetterAuth\Symfony\Feature\TotpService;
use PHPUnit\Framework\TestCase;

final class OptionalBusinessServicesTest extends TestCase
{
    public function testOauthConsumesAtomicStateAndPassesPkceVerifier(): void
    {
        $transactions = new TestAuthorizationTransactionStore();
        $clients = new TestOAuthPort();
        $oauth = new OAuthService($clients, $transactions);
        $request = $oauth->beginAuthorization('github', 'https://app.example.test/oauth/callback');

        if ($request === null) {
            throw new \LogicException('The configured OAuth provider must be allowed.');
        }
        self::assertStringContainsString('state='.$request->state, $request->authorizationUrl);
        self::assertStringContainsString('code_challenge_method=S256', $request->authorizationUrl);
        $outcome = $oauth->completeAuthorization('github', $request->state, 'code');
        self::assertSame(OAuthAuthenticationStatus::Completed, $outcome->status);
        self::assertSame(['id' => 'identity-42'], $outcome->identity);
        self::assertNotSame('', $clients->codeVerifier);
        self::assertSame(OAuthAuthenticationStatus::Invalid, $oauth->completeAuthorization('github', $request->state, 'code')->status);

        $mismatch = $oauth->beginAuthorization('github', 'https://app.example.test/oauth/callback');
        if ($mismatch === null) {
            throw new \LogicException('The configured OAuth provider must be allowed.');
        }
        self::assertSame(OAuthAuthenticationStatus::Invalid, $oauth->completeAuthorization('google', $mismatch->state, 'code')->status);
        self::assertSame(OAuthAuthenticationStatus::Invalid, $oauth->completeAuthorization('github', $mismatch->state, 'code')->status);

        self::assertNull($oauth->beginAuthorization('unknown', 'https://app.example.test/oauth/callback'));
        self::assertSame(2, $transactions->storeCount());
    }

    public function testOidcIsAValidatingRelyingPartyNotAPasetoProvider(): void
    {
        $transactions = new TestAuthorizationTransactionStore();
        $clients = new TestOidcPort();
        $oidc = new OidcService($clients, $transactions, 'https://accounts.example.test');
        $request = $oidc->beginAuthorization('client', 'https://app.example.test/oidc/callback');

        self::assertNotNull($request);
        self::assertStringContainsString('nonce=', $request->authorizationUrl);
        $outcome = $oidc->completeAuthorization('client', $request->state, 'code');
        self::assertSame(OidcAuthenticationStatus::Authenticated, $outcome->status);
        self::assertSame('user-42', $outcome->subject);
        self::assertSame('https://accounts.example.test', $clients->expectedIssuer);
        self::assertSame('client', $clients->expectedAudience);
        self::assertNotSame('', $clients->expectedNonce);
        self::assertSame(OidcAuthenticationStatus::Invalid, $oidc->completeAuthorization('client', $request->state, 'code')->status);
    }

    public function testOneTimeAndDeviceServicesStoreDerivedValuesOnly(): void
    {
        $tokens = new TestOneTimeStore();
        $service = new OneTimeTokenService($tokens, 'magic-link', 60);
        $raw = $service->issue('user-42');
        self::assertNotSame($raw, $tokens->hash);
        self::assertSame('user-42', $service->consume($raw));
        self::assertNull($service->consume($raw));

        $devices = new TestDeviceStore();
        $fingerprint = (new DeviceService($devices))->record('user-42', 'UA', '127.0.0.1');
        self::assertSame($fingerprint, $devices->device['fingerprint']);
    }

    public function testTenantMembershipRemainsApplicationOwned(): void
    {
        self::assertTrue((new TenantMembershipService(new TestTenantStore()))->allows('user-42', 'tenant-1'));
    }

    public function testTotpEnrollmentEncryptsBeforeTheApplicationStore(): void
    {
        $store = new TestTotpStore();
        $enrollment = (new TotpService($store, str_repeat('a', 32), 'Example'))->enroll('user-42', 'alice@example.test');

        self::assertNotSame($enrollment['secret'], $store->ciphertexts['user-42']);
        self::assertStringStartsWith('enc:v1:', $store->ciphertexts['user-42']);
        self::assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', $enrollment['secret']);
        self::assertStringContainsString('otpauth://totp/Example:alice%40example.test', $enrollment['provisioning_uri']);
    }

    public function testTotpRejectsASecretShorterThanThirtyTwoBytes(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new TotpService(new TestTotpStore(), 'too-short');
    }
}

final class TestAuthorizationTransactionStore implements AuthorizationTransactionStoreInterface
{
    /** @var array<string, AuthorizationTransaction> */
    private array $transactions = [];
    private int $writes = 0;

    public function store(AuthorizationTransaction $transaction): void
    {
        ++$this->writes;
        $this->transactions[$transaction->purpose.'|'.$transaction->state] = $transaction;
    }

    public function consume(string $purpose, string $state, \DateTimeImmutable $now): ?AuthorizationTransaction
    {
        $key = $purpose.'|'.$state;
        $transaction = $this->transactions[$key] ?? null;
        unset($this->transactions[$key]);

        return $transaction !== null && $transaction->expiresAt > $now ? $transaction : null;
    }

    public function storeCount(): int
    {
        return $this->writes;
    }
}

final class TestOAuthPort implements OAuthClientPortInterface
{
    public string $codeVerifier = '';

    public function allows(string $provider, string $redirectUri): bool
    {
        return $provider === 'github' && $redirectUri === 'https://app.example.test/oauth/callback';
    }

    public function authorizationUrl(string $provider, string $redirectUri, string $state, string $codeChallenge): string
    {
        return 'https://github.example.test/authorize?state='.$state.'&code_challenge='.$codeChallenge.'&code_challenge_method=S256';
    }

    public function exchangeAuthorizationCode(string $provider, string $code, string $redirectUri, string $codeVerifier): array
    {
        $this->codeVerifier = $codeVerifier;

        return ['id' => 'identity-42'];
    }
}

final class TestOidcPort implements OidcClientPortInterface
{
    public string $expectedIssuer = '';
    public string $expectedAudience = '';
    public string $expectedNonce = '';

    public function allows(string $clientIdentifier, string $redirectUri): bool
    {
        return $clientIdentifier === 'client' && $redirectUri === 'https://app.example.test/oidc/callback';
    }

    public function authorizationUrl(string $issuer, string $clientIdentifier, string $redirectUri, string $state, string $nonce, string $codeChallenge): string
    {
        return $issuer.'/authorize?state='.$state.'&nonce='.$nonce.'&code_challenge='.$codeChallenge;
    }

    public function exchangeAndValidateAuthorizationCode(string $issuer, string $clientIdentifier, string $code, string $redirectUri, string $codeVerifier, string $expectedNonce): OidcIdentityValidationResult
    {
        $this->expectedIssuer = $issuer;
        $this->expectedAudience = $clientIdentifier;
        $this->expectedNonce = $expectedNonce;

        if ($issuer !== 'https://accounts.example.test' || $clientIdentifier !== 'client' || $expectedNonce === '') {
            return OidcIdentityValidationResult::invalid();
        }

        return OidcIdentityValidationResult::valid('user-42', ['iss' => $issuer, 'aud' => $clientIdentifier, 'nonce' => $expectedNonce]);
    }
}

final class TestOneTimeStore implements OneTimeTokenStoreInterface
{
    public string $hash = '';
    private bool $consumed = false;

    public function store(string $purpose, string $tokenHash, string $userIdentifier, \DateTimeImmutable $expiresAt): void
    {
        $this->hash = $tokenHash;
        $this->consumed = false;
    }

    public function consume(string $purpose, string $tokenHash, \DateTimeImmutable $now): ?string
    {
        if ($this->consumed || !hash_equals($this->hash, $tokenHash)) {
            return null;
        }
        $this->consumed = true;

        return 'user-42';
    }
}

final class TestDeviceStore implements DeviceStoreInterface
{
    /** @var array<string, scalar|null> */
    public array $device = [];

    public function record(string $userIdentifier, array $device): void
    {
        $this->device = $device;
    }
}

final class TestTenantStore implements TenantMembershipStoreInterface
{
    public function hasMembership(string $userIdentifier, string $tenantIdentifier): bool
    {
        return $userIdentifier === 'user-42' && $tenantIdentifier === 'tenant-1';
    }
}

final class TestTotpStore implements TotpStoreInterface
{
    /** @var array<string, string> */
    public array $ciphertexts = [];

    public function save(string $userIdentifier, string $ciphertext): void
    {
        $this->ciphertexts[$userIdentifier] = $ciphertext;
    }

    public function load(string $userIdentifier): ?string
    {
        return $this->ciphertexts[$userIdentifier] ?? null;
    }
}
