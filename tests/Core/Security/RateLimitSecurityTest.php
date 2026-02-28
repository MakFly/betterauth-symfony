<?php

declare(strict_types=1);

namespace BetterAuth\Core\Tests\Security;

use BetterAuth\Core\Utils\RateLimiter;
use PHPUnit\Framework\TestCase;

/**
 * Security tests for RateLimiter.
 *
 * These tests verify:
 * - Rate limiting blocks after max attempts
 * - Decay period works correctly
 * - Different keys are isolated
 */
class RateLimitSecurityTest extends TestCase
{
    private RateLimiter $limiter;

    protected function setUp(): void
    {
        $this->limiter = new RateLimiter();
    }

    // ========================================
    // BASIC RATE LIMITING TESTS
    // ========================================

    /**
     * @test
     * Verify that rate limiter allows requests under limit.
     */
    public function requests_under_limit_are_allowed(): void
    {
        $key = 'login:test@example.com';

        for ($i = 0; $i < 4; $i++) {
            $this->assertFalse(
                $this->limiter->tooManyAttempts($key, 5, 60),
                "Request $i should be allowed",
            );
            $this->limiter->hit($key, 60);
        }
    }

    /**
     * @test
     * Verify that rate limiter blocks after max attempts.
     */
    public function requests_over_limit_are_blocked(): void
    {
        $key = 'login:attacker@example.com';

        // Make 5 attempts
        for ($i = 0; $i < 5; $i++) {
            $this->limiter->hit($key, 60);
        }

        // 6th attempt should be blocked
        $this->assertTrue(
            $this->limiter->tooManyAttempts($key, 5, 60),
            'Should be blocked after 5 attempts',
        );
    }

    /**
     * @test
     * Verify that attempts count is tracked correctly.
     */
    public function attempts_are_counted_correctly(): void
    {
        $key = 'login:user@example.com';

        $this->assertEquals(1, $this->limiter->hit($key, 60));
        $this->assertEquals(2, $this->limiter->hit($key, 60));
        $this->assertEquals(3, $this->limiter->hit($key, 60));

        $this->assertEquals(3, $this->limiter->attempts($key));
    }

    // ========================================
    // KEY ISOLATION TESTS
    // ========================================

    /**
     * @test
     * Verify that different keys are isolated.
     */
    public function different_keys_are_isolated(): void
    {
        $key1 = 'login:user1@example.com';
        $key2 = 'login:user2@example.com';

        // Hit key1 5 times
        for ($i = 0; $i < 5; $i++) {
            $this->limiter->hit($key1, 60);
        }

        // key1 should be blocked
        $this->assertTrue($this->limiter->tooManyAttempts($key1, 5, 60));

        // key2 should NOT be blocked
        $this->assertFalse($this->limiter->tooManyAttempts($key2, 5, 60));
    }

    /**
     * @test
     * Verify that similar keys don't interfere.
     */
    public function similar_keys_dont_interfere(): void
    {
        $this->limiter->hit('login:test', 60);
        $this->limiter->hit('login:test', 60);

        $this->assertEquals(2, $this->limiter->attempts('login:test'));
        $this->assertEquals(0, $this->limiter->attempts('login:test2'));
        $this->assertEquals(0, $this->limiter->attempts('login:tes'));
    }

    // ========================================
    // DECAY TESTS
    // ========================================

    /**
     * @test
     * Verify that rate limit resets after decay period.
     */
    public function rate_limit_resets_after_decay(): void
    {
        $key = 'login:decay-test@example.com';

        // Hit 5 times with 1 second decay
        for ($i = 0; $i < 5; $i++) {
            $this->limiter->hit($key, 1);
        }

        $this->assertTrue($this->limiter->tooManyAttempts($key, 5, 1));

        // Wait for decay
        sleep(2);

        // Should be allowed again
        $this->assertFalse($this->limiter->tooManyAttempts($key, 5, 1));
    }

    /**
     * @test
     * Verify availableIn() returns correct time.
     */
    public function available_in_returns_correct_time(): void
    {
        $key = 'login:timing@example.com';

        // Hit with 60 second decay
        $this->limiter->hit($key, 60);

        $availableIn = $this->limiter->availableIn($key);

        // Should be between 55-60 seconds
        $this->assertGreaterThan(50, $availableIn);
        $this->assertLessThanOrEqual(60, $availableIn);
    }

    // ========================================
    // CLEAR TESTS
    // ========================================

    /**
     * @test
     * Verify that clear() resets attempts.
     */
    public function clear_resets_attempts(): void
    {
        $key = 'login:clear-test@example.com';

        for ($i = 0; $i < 5; $i++) {
            $this->limiter->hit($key, 60);
        }

        $this->assertTrue($this->limiter->tooManyAttempts($key, 5, 60));

        $this->limiter->clear($key);

        $this->assertFalse($this->limiter->tooManyAttempts($key, 5, 60));
        $this->assertEquals(0, $this->limiter->attempts($key));
    }

    // ========================================
    // BRUTE FORCE SIMULATION
    // ========================================

    /**
     * @test
     * Simulate brute force attack and verify it's blocked.
     */
    public function brute_force_attack_is_blocked(): void
    {
        $email = 'victim@example.com';
        $key = "login:$email";
        $maxAttempts = 5;
        $decaySeconds = 300; // 5 minutes

        $blocked = false;
        $attemptsMade = 0;

        // Simulate 100 rapid login attempts
        for ($i = 0; $i < 100; $i++) {
            if ($this->limiter->tooManyAttempts($key, $maxAttempts, $decaySeconds)) {
                $blocked = true;
                break;
            }
            $this->limiter->hit($key, $decaySeconds);
            $attemptsMade++;
        }

        $this->assertTrue($blocked, 'Brute force attack should be blocked');
        $this->assertEquals(5, $attemptsMade, 'Should be blocked after exactly 5 attempts');
    }

    /**
     * @test
     * Verify distributed attack (multiple IPs) handling.
     * NOTE: Current in-memory implementation doesn't handle this well.
     * This test documents the limitation.
     */
    public function distributed_attack_per_ip(): void
    {
        $email = 'victim@example.com';

        // Attack from IP 1
        $key1 = "login:{$email}:192.168.1.1";
        for ($i = 0; $i < 5; $i++) {
            $this->limiter->hit($key1, 60);
        }
        $this->assertTrue($this->limiter->tooManyAttempts($key1, 5, 60));

        // Attack from IP 2 - NOT blocked (limitation!)
        $key2 = "login:{$email}:192.168.1.2";
        $this->assertFalse(
            $this->limiter->tooManyAttempts($key2, 5, 60),
            'Different IP is not blocked (known limitation)',
        );

        // To properly handle this, key should be per-email only:
        $emailKey = "login:{$email}";
        // This would block all IPs for same email
    }
}
