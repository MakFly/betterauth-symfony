<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Tests\Feature;

use BetterAuth\Symfony\Feature\DeviceService;
use BetterAuth\Symfony\Feature\OneTimeTokenService;
use BetterAuth\Symfony\Feature\Port\DeviceStoreInterface;
use BetterAuth\Symfony\Feature\Port\OneTimeTokenStoreInterface;
use BetterAuth\Symfony\Feature\Port\TenantMembershipStoreInterface;
use BetterAuth\Symfony\Feature\Port\TotpStoreInterface;
use BetterAuth\Symfony\Feature\TenantMembershipService;
use BetterAuth\Symfony\Feature\TotpService;
use PHPUnit\Framework\TestCase;

final class OptionalBusinessServicesTest extends TestCase
{
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
    }

    public function testTotpRejectsASecretShorterThanThirtyTwoBytes(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new TotpService(new TestTotpStore(), 'too-short');
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
