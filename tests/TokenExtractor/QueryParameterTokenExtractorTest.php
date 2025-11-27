<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Tests\TokenExtractor;

use BetterAuth\Symfony\TokenExtractor\QueryParameterTokenExtractor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests for QueryParameterTokenExtractor.
 */
class QueryParameterTokenExtractorTest extends TestCase
{
    public function testExtractWithQueryParameter(): void
    {
        $extractor = new QueryParameterTokenExtractor();
        $request = new Request(query: ['bearer' => 'query-token-value']);

        $result = $extractor->extract($request);

        $this->assertSame('query-token-value', $result);
    }

    public function testExtractWithCustomParameterName(): void
    {
        $extractor = new QueryParameterTokenExtractor('token');
        $request = new Request(query: ['token' => 'custom-query-token']);

        $result = $extractor->extract($request);

        $this->assertSame('custom-query-token', $result);
    }

    public function testExtractWithNoQueryParameter(): void
    {
        $extractor = new QueryParameterTokenExtractor();
        $request = new Request();

        $result = $extractor->extract($request);

        $this->assertNull($result);
    }

    public function testExtractWithWrongParameterName(): void
    {
        $extractor = new QueryParameterTokenExtractor('expected_param');
        $request = new Request(query: ['other_param' => 'some-value']);

        $result = $extractor->extract($request);

        $this->assertNull($result);
    }

    public function testExtractWithEmptyParameter(): void
    {
        $extractor = new QueryParameterTokenExtractor();
        $request = new Request(query: ['bearer' => '']);

        $result = $extractor->extract($request);

        $this->assertSame('', $result);
    }
}

