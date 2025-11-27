<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Tests\TokenExtractor;

use BetterAuth\Symfony\TokenExtractor\CookieTokenExtractor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests for CookieTokenExtractor.
 */
class CookieTokenExtractorTest extends TestCase
{
    public function testExtractWithCookie(): void
    {
        $extractor = new CookieTokenExtractor();
        $request = new Request(cookies: ['access_token' => 'cookie-token-value']);

        $result = $extractor->extract($request);

        $this->assertSame('cookie-token-value', $result);
    }

    public function testExtractWithCustomCookieName(): void
    {
        $extractor = new CookieTokenExtractor('auth_token');
        $request = new Request(cookies: ['auth_token' => 'custom-cookie-token']);

        $result = $extractor->extract($request);

        $this->assertSame('custom-cookie-token', $result);
    }

    public function testExtractWithNoCookie(): void
    {
        $extractor = new CookieTokenExtractor();
        $request = new Request();

        $result = $extractor->extract($request);

        $this->assertNull($result);
    }

    public function testExtractWithWrongCookieName(): void
    {
        $extractor = new CookieTokenExtractor('expected_cookie');
        $request = new Request(cookies: ['other_cookie' => 'some-value']);

        $result = $extractor->extract($request);

        $this->assertNull($result);
    }

    public function testExtractWithEmptyCookie(): void
    {
        $extractor = new CookieTokenExtractor();
        $request = new Request(cookies: ['access_token' => '']);

        $result = $extractor->extract($request);

        $this->assertSame('', $result);
    }
}

