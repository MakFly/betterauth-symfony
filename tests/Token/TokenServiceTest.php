<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Tests\Token;

use BetterAuth\Symfony\Core\TokenService;
use PHPUnit\Framework\TestCase;

final class TokenServiceTest extends TestCase
{
    public function testItCreatesAndParsesAnAccessToken(): void
    {
        $service = new TokenService(str_repeat('a', 32));
        $token = $service->createAccessToken('user-42', ['tenant' => 'acme'], 60);

        $claims = $service->parseAccessToken($token);

        self::assertSame('user-42', $claims['sub']);
        self::assertSame('access', $claims['type']);
        self::assertSame('acme', $claims['tenant']);
    }
}
