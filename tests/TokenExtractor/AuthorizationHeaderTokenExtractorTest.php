<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Tests\TokenExtractor;

use BetterAuth\Symfony\TokenExtractor\AuthorizationHeaderTokenExtractor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests for AuthorizationHeaderTokenExtractor.
 */
class AuthorizationHeaderTokenExtractorTest extends TestCase
{
    public function testExtractWithBearerToken(): void
    {
        $extractor = new AuthorizationHeaderTokenExtractor();
        $request = new Request();
        $request->headers->set('Authorization', 'Bearer my-access-token');

        $result = $extractor->extract($request);

        $this->assertSame('my-access-token', $result);
    }

    public function testExtractWithCustomPrefix(): void
    {
        $extractor = new AuthorizationHeaderTokenExtractor('Token');
        $request = new Request();
        $request->headers->set('Authorization', 'Token custom-token');

        $result = $extractor->extract($request);

        $this->assertSame('custom-token', $result);
    }

    public function testExtractWithCustomHeaderName(): void
    {
        $extractor = new AuthorizationHeaderTokenExtractor('Bearer', 'X-Auth-Token');
        $request = new Request();
        $request->headers->set('X-Auth-Token', 'Bearer header-token');

        $result = $extractor->extract($request);

        $this->assertSame('header-token', $result);
    }

    public function testExtractWithNoAuthorizationHeader(): void
    {
        $extractor = new AuthorizationHeaderTokenExtractor();
        $request = new Request();

        $result = $extractor->extract($request);

        $this->assertNull($result);
    }

    public function testExtractWithWrongPrefix(): void
    {
        $extractor = new AuthorizationHeaderTokenExtractor('Bearer');
        $request = new Request();
        $request->headers->set('Authorization', 'Basic wrong-prefix-token');

        $result = $extractor->extract($request);

        $this->assertNull($result);
    }

    public function testExtractWithEmptyToken(): void
    {
        $extractor = new AuthorizationHeaderTokenExtractor();
        $request = new Request();
        $request->headers->set('Authorization', 'Bearer ');

        $result = $extractor->extract($request);

        $this->assertSame('', $result);
    }

    public function testExtractWithPasetoToken(): void
    {
        $extractor = new AuthorizationHeaderTokenExtractor();
        $pasetoToken = 'v4.local.abc123def456xyz789';
        $request = new Request();
        $request->headers->set('Authorization', "Bearer $pasetoToken");

        $result = $extractor->extract($request);

        $this->assertSame($pasetoToken, $result);
    }
}

