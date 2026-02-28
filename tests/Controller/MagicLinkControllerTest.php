<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Tests\Controller;

use BetterAuth\Core\Exceptions\RateLimitException;
use BetterAuth\Providers\MagicLinkProvider\MagicLinkProvider;
use BetterAuth\Symfony\Controller\MagicLinkController;
use BetterAuth\Symfony\Tests\Controller\Trait\ControllerTestTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * Unit tests for MagicLinkController.
 *
 * Tests: send magic link, verify magic link (POST and GET).
 */
class MagicLinkControllerTest extends TestCase
{
    use ControllerTestTrait;

    private MockObject&MagicLinkProvider $magicLinkProvider;
    private MagicLinkController $controller;

    protected function setUp(): void
    {
        $this->magicLinkProvider = $this->createMock(MagicLinkProvider::class);
        $this->controller = new MagicLinkController(
            $this->magicLinkProvider,
            null,
            'http://localhost:5173',
        );
        $this->setUpControllerContainer($this->controller);
    }

    private function createJsonRequest(array $data): Request
    {
        return new Request(content: json_encode($data));
    }

    // ========================================
    // SEND MAGIC LINK TESTS
    // ========================================

    /**
     * @test
     */
    public function send_magic_link_returns_200_on_success(): void
    {
        $this->magicLinkProvider->expects($this->once())
            ->method('sendMagicLink')
            ->willReturn(['expiresIn' => 900]);

        $request = $this->createJsonRequest(['email' => 'test@example.com']);
        $response = $this->controller->sendMagicLink($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Magic link sent successfully', $data['message']);
        $this->assertSame(900, $data['expiresIn']);
    }

    /**
     * @test
     */
    public function send_magic_link_returns_400_when_email_missing(): void
    {
        $request = $this->createJsonRequest([]);
        $response = $this->controller->sendMagicLink($request);

        $this->assertSame(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Email is required', $data['error']);
    }

    /**
     * @test
     */
    public function send_magic_link_uses_custom_callback_url(): void
    {
        $this->magicLinkProvider->expects($this->once())
            ->method('sendMagicLink')
            ->with(
                'test@example.com',
                $this->anything(),
                $this->anything(),
                'https://myapp.com/verify-magic-link'
            )
            ->willReturn(['expiresIn' => 900]);

        $request = $this->createJsonRequest([
            'email' => 'test@example.com',
            'callbackUrl' => 'https://myapp.com/verify-magic-link',
        ]);
        $response = $this->controller->sendMagicLink($request);

        $this->assertSame(200, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function send_magic_link_returns_429_on_rate_limit(): void
    {
        $rateLimitException = new RateLimitException('Too many requests', 60);

        $this->magicLinkProvider->method('sendMagicLink')
            ->willThrowException($rateLimitException);

        $request = $this->createJsonRequest(['email' => 'test@example.com']);
        $response = $this->controller->sendMagicLink($request);

        $this->assertSame(429, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('Too many requests', $data['error']);
        $this->assertArrayHasKey('retryAfter', $data);
    }

    /**
     * @test
     */
    public function send_magic_link_returns_500_on_mailer_error(): void
    {
        $transportException = new TransportException('SMTP connection failed');

        $this->magicLinkProvider->method('sendMagicLink')
            ->willThrowException($transportException);

        $request = $this->createJsonRequest(['email' => 'test@example.com']);
        $response = $this->controller->sendMagicLink($request);

        $this->assertSame(500, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('Failed to send email', $data['error']);
    }

    /**
     * @test
     */
    public function send_magic_link_returns_500_on_generic_exception(): void
    {
        $this->magicLinkProvider->method('sendMagicLink')
            ->willThrowException(new \Exception('Unexpected error'));

        $request = $this->createJsonRequest(['email' => 'test@example.com']);
        $response = $this->controller->sendMagicLink($request);

        $this->assertSame(500, $response->getStatusCode());
    }

    // ========================================
    // VERIFY MAGIC LINK (POST) TESTS
    // ========================================

    /**
     * @test
     */
    public function verify_magic_link_post_returns_tokens_on_success(): void
    {
        $this->magicLinkProvider->expects($this->once())
            ->method('verifyMagicLink')
            ->with('valid-magic-token', $this->anything(), $this->anything())
            ->willReturn([
                'success' => true,
                'access_token' => 'new-access-token',
                'refresh_token' => 'new-refresh-token',
                'expires_in' => 3600,
                'user' => ['id' => 'uuid-1', 'email' => 'test@example.com'],
            ]);

        $request = $this->createJsonRequest(['token' => 'valid-magic-token']);
        $response = $this->controller->verifyMagicLink($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('new-access-token', $data['access_token']);
        $this->assertSame('Bearer', $data['token_type']);
        $this->assertArrayHasKey('user', $data);
    }

    /**
     * @test
     */
    public function verify_magic_link_post_returns_400_when_token_missing(): void
    {
        $request = $this->createJsonRequest([]);
        $response = $this->controller->verifyMagicLink($request);

        $this->assertSame(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Magic link token is required', $data['error']);
    }

    /**
     * @test
     */
    public function verify_magic_link_post_returns_400_on_invalid_token(): void
    {
        $this->magicLinkProvider->method('verifyMagicLink')
            ->willReturn([
                'success' => false,
                'error' => 'Token has expired',
            ]);

        $request = $this->createJsonRequest(['token' => 'expired-token']);
        $response = $this->controller->verifyMagicLink($request);

        $this->assertSame(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Token has expired', $data['error']);
    }

    /**
     * @test
     */
    public function verify_magic_link_post_returns_400_on_exception(): void
    {
        $this->magicLinkProvider->method('verifyMagicLink')
            ->willThrowException(new \Exception('Internal error'));

        $request = $this->createJsonRequest(['token' => 'some-token']);
        $response = $this->controller->verifyMagicLink($request);

        $this->assertSame(400, $response->getStatusCode());
    }

    // ========================================
    // VERIFY MAGIC LINK (GET) TESTS
    // ========================================

    /**
     * @test
     */
    public function verify_magic_link_get_returns_tokens_on_success(): void
    {
        $this->magicLinkProvider->method('verifyMagicLink')
            ->with('valid-token-path', $this->anything(), $this->anything())
            ->willReturn([
                'success' => true,
                'access_token' => 'access-tok',
                'refresh_token' => 'refresh-tok',
                'expires_in' => 3600,
                'user' => ['id' => 'uuid-1'],
            ]);

        $request = new Request();
        $response = $this->controller->verifyMagicLinkGet('valid-token-path', $request);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('access-tok', $data['access_token']);
    }

    /**
     * @test
     */
    public function verify_magic_link_get_returns_400_on_invalid_token(): void
    {
        $this->magicLinkProvider->method('verifyMagicLink')
            ->willReturn([
                'success' => false,
                'error' => 'Invalid or expired magic link',
            ]);

        $request = new Request();
        $response = $this->controller->verifyMagicLinkGet('bad-token', $request);

        $this->assertSame(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Invalid or expired magic link', $data['error']);
    }
}
