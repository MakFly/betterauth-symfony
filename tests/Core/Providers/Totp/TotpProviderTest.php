<?php

declare(strict_types=1);

namespace BetterAuth\Core\Tests\Providers\Totp;

use BetterAuth\Core\Interfaces\TotpStorageInterface;
use BetterAuth\Providers\TotpProvider\TotpProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests for TotpProvider.
 *
 * Covers:
 * - generateSecret() — secret generation and storage
 * - verify() — valid/invalid code, backup codes, not-enabled user
 * - verifyAndEnable() — full flow
 * - disable() — requires backup code
 * - regenerateBackupCodes()
 * - getStatus() / requires2fa()
 * - Algorithm migration detection
 */
class TotpProviderTest extends TestCase
{
    private TotpStorageInterface&MockObject $totpStorage;
    private TotpProvider $totpProvider;

    protected function setUp(): void
    {
        $this->totpStorage = $this->createMock(TotpStorageInterface::class);
        $this->totpProvider = new TotpProvider(
            totpStorage: $this->totpStorage,
            issuer: 'TestApp',
        );
    }

    // ========================================
    // generateSecret() TESTS
    // ========================================

    public function testGenerateSecretReturnsRequiredKeys(): void
    {
        $this->totpStorage
            ->expects($this->once())
            ->method('store')
            ->willReturn(true);

        $result = $this->totpProvider->generateSecret('user-1');

        $this->assertArrayHasKey('secret', $result);
        $this->assertArrayHasKey('qrCode', $result);
        $this->assertArrayHasKey('qrCodeUrl', $result);
        $this->assertArrayHasKey('manualEntryKey', $result);
        $this->assertArrayHasKey('backupCodes', $result);
    }

    public function testGenerateSecretCreatesBase32Secret(): void
    {
        $this->totpStorage->method('store')->willReturn(true);

        $result = $this->totpProvider->generateSecret('user-1');

        // Base32 only contains uppercase letters A-Z and digits 2-7
        $this->assertMatchesRegularExpression('/^[A-Z2-7]+$/', $result['secret']);
    }

    public function testGenerateSecretCreates10BackupCodes(): void
    {
        $this->totpStorage->method('store')->willReturn(true);

        $result = $this->totpProvider->generateSecret('user-1');

        $this->assertCount(10, $result['backupCodes']);
    }

    public function testGenerateSecretQrCodeUrlContainsIssuerAndUser(): void
    {
        $this->totpStorage->method('store')->willReturn(true);

        $result = $this->totpProvider->generateSecret('user-1', 'user@example.com');

        $this->assertStringContainsString('otpauth://totp/', $result['qrCode']);
        $this->assertStringContainsString('TestApp', $result['qrCode']);
        $this->assertStringContainsString('user%40example.com', $result['qrCode']);
    }

    public function testGenerateSecretFallsBackToUserIdWhenNoDisplayIdentifier(): void
    {
        $this->totpStorage->method('store')->willReturn(true);

        $result = $this->totpProvider->generateSecret('user-123');

        $this->assertStringContainsString('user-123', $result['qrCode']);
    }

    public function testGenerateSecretStoresWithAlgorithmSha256(): void
    {
        $this->totpStorage
            ->expects($this->once())
            ->method('store')
            ->with(
                'user-1',
                $this->isType('string'),
                $this->callback(function (array $metadata): bool {
                    return ($metadata['algorithm'] ?? '') === 'sha256'
                        && $metadata['enabled'] === false;
                }),
            )
            ->willReturn(true);

        $this->totpProvider->generateSecret('user-1');
    }

    // ========================================
    // verify() TESTS
    // ========================================

    public function testVerifyReturnsFalseWhenTotpNotConfigured(): void
    {
        $this->totpStorage->method('findByUserId')->willReturn(null);

        $result = $this->totpProvider->verify('user-1', '123456');

        $this->assertFalse($result);
    }

    public function testVerifyReturnsFalseWhenTotpNotEnabled(): void
    {
        $this->totpStorage->method('findByUserId')->willReturn([
            'secret' => 'JBSWY3DPEHPK3PXP',
            'enabled' => false,
            'backup_codes' => [],
        ]);

        $result = $this->totpProvider->verify('user-1', '123456');

        $this->assertFalse($result);
    }

    public function testVerifyReturnsFalseForInvalidCode(): void
    {
        $this->totpStorage->method('findByUserId')->willReturn([
            'secret' => 'JBSWY3DPEHPK3PXP',
            'enabled' => true,
            'backup_codes' => [],
        ]);

        $this->totpStorage->method('useBackupCode')->willReturn(false);

        $result = $this->totpProvider->verify('user-1', '000000');

        $this->assertFalse($result);
    }

    public function testVerifyUpdatesTimestampOnSuccessfulBackupCode(): void
    {
        $this->totpStorage->method('findByUserId')->willReturn([
            'secret' => 'JBSWY3DPEHPK3PXP',
            'enabled' => true,
            'backup_codes' => ['remaining-code'],
        ]);

        // TOTP code fails, but backup code succeeds
        $this->totpStorage->method('useBackupCode')->willReturn(true);

        $this->totpStorage
            ->expects($this->once())
            ->method('updateLast2faVerifiedAt')
            ->with('user-1');

        $result = $this->totpProvider->verify('user-1', 'BACKUPCODE01');

        $this->assertTrue($result);
    }

    public function testVerifyLogsWarningWhenLastBackupCodeUsed(): void
    {
        // Storage returns empty backup_codes after using the last one
        $this->totpStorage->method('findByUserId')->willReturnOnConsecutiveCalls(
            // First call (in verify, before useBackupCode)
            ['secret' => 'JBSWY3DPEHPK3PXP', 'enabled' => true, 'backup_codes' => ['last-code']],
            // Second call (after backup code used, checking remaining)
            ['secret' => 'JBSWY3DPEHPK3PXP', 'enabled' => true, 'backup_codes' => []],
        );

        $this->totpStorage->method('useBackupCode')->willReturn(true);
        $this->totpStorage->method('updateLast2faVerifiedAt')->willReturn(true);

        // Should not throw — just logs a warning
        $result = $this->totpProvider->verify('user-1', 'last-code');

        $this->assertTrue($result);
    }

    // ========================================
    // verifyAndEnable() TESTS
    // ========================================

    public function testVerifyAndEnableReturnsFalseWhenNotConfigured(): void
    {
        $this->totpStorage->method('findByUserId')->willReturn(null);

        $result = $this->totpProvider->verifyAndEnable('user-1', '123456');

        $this->assertFalse($result);
    }

    public function testVerifyAndEnableReturnsFalseForInvalidCode(): void
    {
        $this->totpStorage->method('findByUserId')->willReturn([
            'secret' => 'JBSWY3DPEHPK3PXP',
            'enabled' => false,
            'backup_codes' => [],
        ]);

        // Invalid code (all zeros is almost certainly wrong)
        $result = $this->totpProvider->verifyAndEnable('user-1', '000000');

        $this->assertFalse($result);
        $this->totpStorage->expects($this->never())->method('enable');
    }

    public function testVerifyAndEnableEnablesOnValidCode(): void
    {
        // We need to generate a real valid code to test this
        // We use a known secret and compute the expected code
        $secret = 'JBSWY3DPEHPK3PXP';
        $validCode = $this->computeTotpCode($secret, time());

        $this->totpStorage->method('findByUserId')->willReturn([
            'secret' => $secret,
            'enabled' => false,
            'backup_codes' => [],
        ]);

        $this->totpStorage
            ->expects($this->once())
            ->method('enable')
            ->with('user-1')
            ->willReturn(true);

        $result = $this->totpProvider->verifyAndEnable('user-1', $validCode);

        $this->assertTrue($result);
    }

    // ========================================
    // disable() TESTS
    // ========================================

    public function testDisableReturnsFalseWhenNotConfigured(): void
    {
        $this->totpStorage->method('findByUserId')->willReturn(null);

        $result = $this->totpProvider->disable('user-1', 'backup-code');

        $this->assertFalse($result);
    }

    public function testDisableReturnsFalseWhenNotEnabled(): void
    {
        $this->totpStorage->method('findByUserId')->willReturn([
            'secret' => 'JBSWY3DPEHPK3PXP',
            'enabled' => false,
            'backup_codes' => [],
        ]);

        $result = $this->totpProvider->disable('user-1', 'backup-code');

        $this->assertFalse($result);
    }

    public function testDisableReturnsFalseOnInvalidBackupCode(): void
    {
        $this->totpStorage->method('findByUserId')->willReturn([
            'secret' => 'JBSWY3DPEHPK3PXP',
            'enabled' => true,
            'backup_codes' => ['hashed-code'],
        ]);

        $this->totpStorage->method('useBackupCode')->willReturn(false);

        $result = $this->totpProvider->disable('user-1', 'wrong-backup-code');

        $this->assertFalse($result);
    }

    public function testDisableSucceedsWithValidBackupCode(): void
    {
        $this->totpStorage->method('findByUserId')->willReturn([
            'secret' => 'JBSWY3DPEHPK3PXP',
            'enabled' => true,
            'backup_codes' => ['hashed-code'],
        ]);

        $this->totpStorage->method('useBackupCode')->willReturn(true);

        $this->totpStorage
            ->expects($this->once())
            ->method('disable')
            ->with('user-1')
            ->willReturn(true);

        $result = $this->totpProvider->disable('user-1', 'valid-backup-code');

        $this->assertTrue($result);
    }

    // ========================================
    // regenerateBackupCodes() TESTS
    // ========================================

    public function testRegenerateBackupCodesFailsWhenNotConfigured(): void
    {
        $this->totpStorage->method('findByUserId')->willReturn(null);

        $result = $this->totpProvider->regenerateBackupCodes('user-1', '123456');

        $this->assertFalse($result['success']);
        $this->assertSame('TOTP not configured for user', $result['error']);
    }

    public function testRegenerateBackupCodesFailsOnInvalidCode(): void
    {
        $this->totpStorage->method('findByUserId')->willReturn([
            'secret' => 'JBSWY3DPEHPK3PXP',
            'enabled' => true,
            'backup_codes' => [],
        ]);

        $result = $this->totpProvider->regenerateBackupCodes('user-1', '000000');

        $this->assertFalse($result['success']);
        $this->assertSame('Invalid verification code', $result['error']);
    }

    public function testRegenerateBackupCodesReturns10NewCodes(): void
    {
        $secret = 'JBSWY3DPEHPK3PXP';
        $validCode = $this->computeTotpCode($secret, time());

        $this->totpStorage->method('findByUserId')->willReturn([
            'secret' => $secret,
            'enabled' => true,
            'backup_codes' => ['old-code'],
        ]);

        $this->totpStorage->method('store')->willReturn(true);

        $result = $this->totpProvider->regenerateBackupCodes('user-1', $validCode);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('backupCodes', $result);
        $this->assertCount(10, $result['backupCodes']);
    }

    // ========================================
    // getStatus() TESTS
    // ========================================

    public function testGetStatusReturnsFalseWhenNotConfigured(): void
    {
        $this->totpStorage->method('findByUserId')->willReturn(null);

        $status = $this->totpProvider->getStatus('user-1');

        $this->assertFalse($status['enabled']);
    }

    public function testGetStatusReturnsEnabledAndBackupCodesCount(): void
    {
        $this->totpStorage->method('findByUserId')->willReturn([
            'secret' => 'JBSWY3DPEHPK3PXP',
            'enabled' => true,
            'backup_codes' => ['a', 'b', 'c'],
            'last_2fa_verified_at' => date('Y-m-d H:i:s'), // Just verified
        ]);

        $status = $this->totpProvider->getStatus('user-1');

        $this->assertTrue($status['enabled']);
        $this->assertSame(3, $status['backupCodesRemaining']);
        $this->assertFalse($status['requires2fa']); // Just verified, so not required
    }

    public function testGetStatusRequires2faWhenNeverVerified(): void
    {
        $this->totpStorage->method('findByUserId')->willReturn([
            'secret' => 'JBSWY3DPEHPK3PXP',
            'enabled' => true,
            'backup_codes' => [],
            'last_2fa_verified_at' => null,
        ]);

        $status = $this->totpProvider->getStatus('user-1');

        $this->assertTrue($status['requires2fa']);
    }

    // ========================================
    // requires2fa() TESTS
    // ========================================

    public function testRequires2faReturnsFalseWhenNotEnabled(): void
    {
        $this->totpStorage->method('findByUserId')->willReturn(null);

        $this->assertFalse($this->totpProvider->requires2fa('user-1'));
    }

    public function testRequires2faReturnsTrueWhenNeverVerified(): void
    {
        $this->totpStorage->method('findByUserId')->willReturn([
            'secret' => 'JBSWY3DPEHPK3PXP',
            'enabled' => true,
            'backup_codes' => [],
            'last_2fa_verified_at' => null,
        ]);

        $this->assertTrue($this->totpProvider->requires2fa('user-1'));
    }

    // ========================================
    // validateCode() TESTS
    // ========================================

    public function testValidateCodeAlwaysThrowsLogicException(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/Use TotpProvider::verify\(\$userId, \$code\)/');

        $this->totpProvider->validateCode('user@example.com', '123456');
    }

    // ========================================
    // Algorithm migration TESTS
    // ========================================

    public function testGetUserAlgorithmReturnsSha256ByDefault(): void
    {
        $this->totpStorage->method('findByUserId')->willReturn(null);

        $algo = $this->totpProvider->getUserAlgorithm('user-1');

        $this->assertSame('sha256', $algo);
    }

    public function testGetUserAlgorithmReturnsSha1ForLegacySecrets(): void
    {
        $this->totpStorage->method('findByUserId')->willReturn([
            'secret' => 'LEGACYSECRET',
            'enabled' => true,
            'backup_codes' => [],
            // No 'algorithm' key => legacy SHA-1
        ]);

        $algo = $this->totpProvider->getUserAlgorithm('user-1');

        $this->assertSame('sha1', $algo);
    }

    public function testNeedsAlgorithmMigrationReturnsTrueForSha1(): void
    {
        $this->totpStorage->method('findByUserId')->willReturn([
            'secret' => 'LEGACYSECRET',
            'enabled' => true,
            'backup_codes' => [],
            'algorithm' => 'sha1',
        ]);

        $this->assertTrue($this->totpProvider->needsAlgorithmMigration('user-1'));
    }

    public function testNeedsAlgorithmMigrationReturnsFalseForSha256(): void
    {
        $this->totpStorage->method('findByUserId')->willReturn([
            'secret' => 'NEWSECRET',
            'enabled' => true,
            'backup_codes' => [],
            'algorithm' => 'sha256',
        ]);

        $this->assertFalse($this->totpProvider->needsAlgorithmMigration('user-1'));
    }

    // ========================================
    // resetWithBackupCode() TESTS
    // ========================================

    public function testResetWithBackupCodeFailsWhenNotConfigured(): void
    {
        $this->totpStorage->method('findByUserId')->willReturn(null);

        $result = $this->totpProvider->resetWithBackupCode('user-1', 'backup-code');

        $this->assertFalse($result['success']);
    }

    public function testResetWithBackupCodeFailsWithInvalidBackupCode(): void
    {
        $this->totpStorage->method('findByUserId')->willReturn([
            'secret' => 'JBSWY3DPEHPK3PXP',
            'enabled' => true,
            'backup_codes' => ['code1'],
        ]);

        $this->totpStorage->method('useBackupCode')->willReturn(false);

        $result = $this->totpProvider->resetWithBackupCode('user-1', 'wrong-code');

        $this->assertFalse($result['success']);
        $this->assertSame('Invalid backup code', $result['error']);
    }

    public function testResetWithBackupCodeSucceedsAndReturnsNewSecret(): void
    {
        $this->totpStorage->method('findByUserId')->willReturn([
            'secret' => 'JBSWY3DPEHPK3PXP',
            'enabled' => true,
            'backup_codes' => ['code1'],
        ]);

        $this->totpStorage->method('useBackupCode')->willReturn(true);
        $this->totpStorage->method('store')->willReturn(true);

        $result = $this->totpProvider->resetWithBackupCode('user-1', 'valid-backup');

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('secret', $result);
        $this->assertArrayHasKey('qrCode', $result);
        $this->assertArrayHasKey('backupCodes', $result);
        $this->assertCount(10, $result['backupCodes']);
    }

    // ========================================
    // HELPERS
    // ========================================

    /**
     * Compute a real TOTP code for a given secret and timestamp.
     * Uses SHA-256 (the default algorithm).
     */
    private function computeTotpCode(string $secret, int $timestamp, string $algorithm = 'sha256'): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $decodedSecret = '';
        $bits = 0;
        $value = 0;

        foreach (str_split(strtoupper($secret)) as $char) {
            if (($pos = strpos($alphabet, $char)) === false) {
                continue;
            }
            $value = ($value << 5) | $pos;
            $bits += 5;
            if ($bits >= 8) {
                $decodedSecret .= chr(($value >> ($bits - 8)) & 255);
                $bits -= 8;
            }
        }

        $time = pack('N*', 0) . pack('N*', (int) floor($timestamp / 30));
        $hash = hash_hmac($algorithm, $time, $decodedSecret, true);
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $code = (
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF)
        ) % 1000000;

        return str_pad((string) $code, 6, '0', STR_PAD_LEFT);
    }
}
