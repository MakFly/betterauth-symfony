<?php

declare(strict_types=1);

namespace BetterAuth\Core\Tests\Security;

use BetterAuth\Core\PasswordHasher;
use PHPUnit\Framework\TestCase;

/**
 * Security tests for PasswordHasher (Argon2id).
 *
 * These tests verify:
 * - Argon2id algorithm is used
 * - Timing-safe comparison
 * - Hash uniqueness (salting)
 * - Rehashing detection
 */
class PasswordSecurityTest extends TestCase
{
    private PasswordHasher $hasher;

    protected function setUp(): void
    {
        $this->hasher = new PasswordHasher();
    }

    // ========================================
    // ALGORITHM TESTS
    // ========================================

    /**
     * @test
     * Verify that Argon2id algorithm is used.
     */
    public function argon2id_algorithm_is_used(): void
    {
        $hash = $this->hasher->hash('password123');

        // Argon2id hashes start with $argon2id$
        $this->assertStringStartsWith('$argon2id$', $hash);
    }

    /**
     * @test
     * Verify that hash info contains correct algorithm.
     */
    public function hash_info_shows_argon2id(): void
    {
        $hash = $this->hasher->hash('password123');
        $info = password_get_info($hash);

        $this->assertEquals(PASSWORD_ARGON2ID, $info['algo']);
        $this->assertEquals('argon2id', $info['algoName']);
    }

    // ========================================
    // VERIFICATION TESTS
    // ========================================

    /**
     * @test
     * Verify that correct password is accepted.
     */
    public function correct_password_is_verified(): void
    {
        $password = 'SecurePassword123!';
        $hash = $this->hasher->hash($password);

        $this->assertTrue($this->hasher->verify($password, $hash));
    }

    /**
     * @test
     * Verify that incorrect password is rejected.
     */
    public function incorrect_password_is_rejected(): void
    {
        $hash = $this->hasher->hash('correctPassword');

        $this->assertFalse($this->hasher->verify('wrongPassword', $hash));
    }

    /**
     * @test
     * Verify that empty password is rejected against valid hash.
     */
    public function empty_password_is_rejected(): void
    {
        $hash = $this->hasher->hash('somePassword');

        $this->assertFalse($this->hasher->verify('', $hash));
    }

    /**
     * @test
     * Verify that password verification is case-sensitive.
     */
    public function password_verification_is_case_sensitive(): void
    {
        $hash = $this->hasher->hash('Password123');

        $this->assertFalse($this->hasher->verify('password123', $hash));
        $this->assertFalse($this->hasher->verify('PASSWORD123', $hash));
        $this->assertTrue($this->hasher->verify('Password123', $hash));
    }

    // ========================================
    // SALTING TESTS (Hash Uniqueness)
    // ========================================

    /**
     * @test
     * Verify that same password produces different hashes (salting).
     */
    public function same_password_produces_different_hashes(): void
    {
        $password = 'SamePassword123';

        $hash1 = $this->hasher->hash($password);
        $hash2 = $this->hasher->hash($password);

        // Hashes should be different due to random salt
        $this->assertNotEquals($hash1, $hash2);

        // But both should verify correctly
        $this->assertTrue($this->hasher->verify($password, $hash1));
        $this->assertTrue($this->hasher->verify($password, $hash2));
    }

    /**
     * @test
     * Verify that 100 hashes of same password are all unique.
     */
    public function multiple_hashes_are_unique(): void
    {
        $password = 'TestPassword';
        $hashes = [];

        for ($i = 0; $i < 100; $i++) {
            $hashes[] = $this->hasher->hash($password);
        }

        // All hashes should be unique
        $uniqueHashes = array_unique($hashes);
        $this->assertCount(100, $uniqueHashes);
    }

    // ========================================
    // REHASHING TESTS
    // ========================================

    /**
     * @test
     * Verify that needsRehash() returns false for fresh hash.
     */
    public function fresh_hash_does_not_need_rehash(): void
    {
        $hash = $this->hasher->hash('password123');

        $this->assertFalse($this->hasher->needsRehash($hash));
    }

    /**
     * @test
     * Verify that old algorithm hash needs rehash.
     */
    public function old_algorithm_needs_rehash(): void
    {
        // Create a bcrypt hash (old algorithm)
        $oldHash = password_hash('password123', PASSWORD_BCRYPT);

        $this->assertTrue($this->hasher->needsRehash($oldHash));
    }

    /**
     * @test
     * Verify that hash with different parameters needs rehash.
     */
    public function different_parameters_need_rehash(): void
    {
        // Create hasher with custom (higher) parameters
        $strongerHasher = new PasswordHasher(
            memoryCost: 131072, // 128MB
            timeCost: 6,
            threads: 4,
        );

        // Hash with default parameters
        $weakerHash = $this->hasher->hash('password123');

        // Stronger hasher should detect this needs rehash
        $this->assertTrue($strongerHasher->needsRehash($weakerHash));
    }

    // ========================================
    // TIMING ATTACK RESISTANCE
    // ========================================

    /**
     * @test
     * Verify that verification time is consistent regardless of password correctness.
     * This helps prevent timing attacks.
     */
    public function verification_time_is_consistent(): void
    {
        $hash = $this->hasher->hash('correctPassword');

        // Measure time for correct password
        $times = [];
        for ($i = 0; $i < 10; $i++) {
            $start = hrtime(true);
            $this->hasher->verify('correctPassword', $hash);
            $times['correct'][] = hrtime(true) - $start;
        }

        // Measure time for wrong password
        for ($i = 0; $i < 10; $i++) {
            $start = hrtime(true);
            $this->hasher->verify('wrongPassword', $hash);
            $times['wrong'][] = hrtime(true) - $start;
        }

        $avgCorrect = array_sum($times['correct']) / count($times['correct']);
        $avgWrong = array_sum($times['wrong']) / count($times['wrong']);

        // Times should be within 50% of each other
        // password_verify() is timing-safe by design
        $ratio = max($avgCorrect, $avgWrong) / max(1, min($avgCorrect, $avgWrong));
        $this->assertLessThan(2, $ratio, 'Verification times should be similar');
    }

    // ========================================
    // SPECIAL CHARACTER TESTS
    // ========================================

    /**
     * @test
     * Verify that passwords with special characters work correctly.
     */
    public function special_characters_in_password_work(): void
    {
        $passwords = [
            'P@$$w0rd!#%',
            "Pass'word\"test",
            'Unicode: 日本語パスワード',
            'Emoji: 🔐🔑🛡️',
            'Spaces: pass word with spaces',
            "Newlines:\nand\ttabs",
            'Very' . str_repeat('Long', 100) . 'Password',
        ];

        foreach ($passwords as $password) {
            $hash = $this->hasher->hash($password);
            $this->assertTrue(
                $this->hasher->verify($password, $hash),
                'Failed for password: ' . substr($password, 0, 30) . '...',
            );
        }
    }

    /**
     * @test
     * Verify that null bytes in password don't cause issues.
     */
    public function null_bytes_are_handled(): void
    {
        $password = "pass\x00word";
        $hash = $this->hasher->hash($password);

        $this->assertTrue($this->hasher->verify($password, $hash));
        $this->assertFalse($this->hasher->verify('pass', $hash));
        $this->assertFalse($this->hasher->verify('password', $hash));
    }
}
