<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Tests\Security;

use BetterAuth\Symfony\Controller\Trait\CallbackUrlValidatorTrait;
use PHPUnit\Framework\TestCase;

/**
 * SEC-21 — the callback-URL validator must also match the scheme, so an attacker
 * cannot downgrade an https frontend to http:// and exfiltrate the one-time token.
 */
final class CallbackUrlValidatorTest extends TestCase
{
    private object $validator;

    protected function setUp(): void
    {
        $this->validator = new class {
            use CallbackUrlValidatorTrait;

            public function check(string $cb, string $fe): bool
            {
                return $this->isAllowedCallbackUrl($cb, $fe);
            }
        };
    }

    public function testSchemeDowngradeIsRejected(): void
    {
        self::assertFalse(
            $this->validator->check('http://app.example.com/cb', 'https://app.example.com')
        );
    }

    public function testMatchingSchemeHostPortIsAllowed(): void
    {
        self::assertTrue(
            $this->validator->check('https://app.example.com/cb?x=1', 'https://app.example.com')
        );
    }

    public function testDifferentHostIsRejected(): void
    {
        self::assertFalse(
            $this->validator->check('https://evil.example.com/cb', 'https://app.example.com')
        );
    }

    public function testNonHttpSchemeIsRejected(): void
    {
        self::assertFalse(
            $this->validator->check('javascript:alert(1)', 'https://app.example.com')
        );
    }
}
