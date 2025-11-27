<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Tests\TokenExtractor;

use BetterAuth\Symfony\TokenExtractor\AuthorizationHeaderTokenExtractor;
use BetterAuth\Symfony\TokenExtractor\ChainTokenExtractor;
use BetterAuth\Symfony\TokenExtractor\CookieTokenExtractor;
use BetterAuth\Symfony\TokenExtractor\QueryParameterTokenExtractor;
use BetterAuth\Symfony\TokenExtractor\TokenExtractorInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests for ChainTokenExtractor.
 */
class ChainTokenExtractorTest extends TestCase
{
    public function testExtractFromFirstExtractor(): void
    {
        $chain = new ChainTokenExtractor([
            new AuthorizationHeaderTokenExtractor(),
            new CookieTokenExtractor(),
        ]);

        $request = new Request();
        $request->headers->set('Authorization', 'Bearer header-token');

        $result = $chain->extract($request);

        $this->assertSame('header-token', $result);
    }

    public function testExtractFallbackToSecondExtractor(): void
    {
        $chain = new ChainTokenExtractor([
            new AuthorizationHeaderTokenExtractor(),
            new CookieTokenExtractor(),
        ]);

        $request = new Request(cookies: ['access_token' => 'cookie-token']);
        // No Authorization header

        $result = $chain->extract($request);

        $this->assertSame('cookie-token', $result);
    }

    public function testExtractFallbackToThirdExtractor(): void
    {
        $chain = new ChainTokenExtractor([
            new AuthorizationHeaderTokenExtractor(),
            new CookieTokenExtractor(),
            new QueryParameterTokenExtractor(),
        ]);

        $request = new Request(query: ['bearer' => 'query-token']);
        // No Authorization header, no cookie

        $result = $chain->extract($request);

        $this->assertSame('query-token', $result);
    }

    public function testExtractReturnsNullWhenNoExtractorMatches(): void
    {
        $chain = new ChainTokenExtractor([
            new AuthorizationHeaderTokenExtractor(),
            new CookieTokenExtractor(),
        ]);

        $request = new Request();
        // No header, no cookie

        $result = $chain->extract($request);

        $this->assertNull($result);
    }

    public function testExtractWithEmptyChain(): void
    {
        $chain = new ChainTokenExtractor([]);
        $request = new Request();
        $request->headers->set('Authorization', 'Bearer some-token');

        $result = $chain->extract($request);

        $this->assertNull($result);
    }

    public function testAddExtractor(): void
    {
        $chain = new ChainTokenExtractor();
        $chain->addExtractor(new AuthorizationHeaderTokenExtractor());

        $request = new Request();
        $request->headers->set('Authorization', 'Bearer added-token');

        $result = $chain->extract($request);

        $this->assertSame('added-token', $result);
    }

    public function testAddExtractorReturnsChain(): void
    {
        $chain = new ChainTokenExtractor();

        $result = $chain->addExtractor(new AuthorizationHeaderTokenExtractor());

        $this->assertSame($chain, $result);
    }

    public function testGetExtractors(): void
    {
        $extractor1 = new AuthorizationHeaderTokenExtractor();
        $extractor2 = new CookieTokenExtractor();

        $chain = new ChainTokenExtractor([$extractor1, $extractor2]);

        $extractors = $chain->getExtractors();

        $this->assertCount(2, $extractors);
        $this->assertSame($extractor1, $extractors[0]);
        $this->assertSame($extractor2, $extractors[1]);
    }

    public function testClearExtractors(): void
    {
        $chain = new ChainTokenExtractor([
            new AuthorizationHeaderTokenExtractor(),
            new CookieTokenExtractor(),
        ]);

        $chain->clearExtractors();

        $this->assertCount(0, $chain->getExtractors());
    }

    public function testClearExtractorsReturnsChain(): void
    {
        $chain = new ChainTokenExtractor();

        $result = $chain->clearExtractors();

        $this->assertSame($chain, $result);
    }

    public function testPriorityOfExtractors(): void
    {
        // First extractor should take precedence even if multiple could match
        $chain = new ChainTokenExtractor([
            new AuthorizationHeaderTokenExtractor(),
            new CookieTokenExtractor(),
        ]);

        $request = new Request(cookies: ['access_token' => 'cookie-token']);
        $request->headers->set('Authorization', 'Bearer header-token');

        $result = $chain->extract($request);

        $this->assertSame('header-token', $result);
    }

    public function testImplementsInterface(): void
    {
        $chain = new ChainTokenExtractor();

        $this->assertInstanceOf(TokenExtractorInterface::class, $chain);
    }
}

