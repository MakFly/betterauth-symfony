<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Tests\Token;

use BetterAuth\Symfony\Core\Exceptions\InvalidTokenException;
use BetterAuth\Symfony\Core\TokenService;
use BetterAuth\Symfony\TokenExtractor\AuthorizationHeaderTokenExtractor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class TokenLimitsTest extends TestCase
{
    public function testParserRejectsOversizedTokensAndClaims(): void
    {
        $issuer = new TokenService(str_repeat('a', 32));
        $token = $issuer->createAccessToken('user-42', ['payload' => str_repeat('x', 512)], 60);

        $limited = new TokenService(str_repeat('a', 32), maxTokenLength: 8192, maxJsonLength: 128);
        $this->expectException(InvalidTokenException::class);
        $limited->parseAccessToken($token);
    }

    public function testParserRejectsTooManyClaimsBeforeAuthentication(): void
    {
        $issuer = new TokenService(str_repeat('a', 32));
        $token = $issuer->createAccessToken('user-42', ['one' => 1, 'two' => 2, 'three' => 3], 60);

        $limited = new TokenService(str_repeat('a', 32), maxClaimCount: 2);
        $this->expectException(InvalidTokenException::class);
        $limited->parseAccessToken($token);
    }

    public function testHeaderExtractorRejectsOversizedOrAmbiguousBearerValues(): void
    {
        $extractor = new AuthorizationHeaderTokenExtractor(32);
        self::assertNull($extractor->extract(Request::create('/', server: ['HTTP_AUTHORIZATION' => 'Bearer '.str_repeat('x', 40)])));
        self::assertNull($extractor->extract(Request::create('/', server: ['HTTP_AUTHORIZATION' => 'Bearer token trailing'])));
    }
}
