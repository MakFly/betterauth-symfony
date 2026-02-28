<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Tests\Controller;

use BetterAuth\Core\AuthManager;
use BetterAuth\Core\Entities\User;
use BetterAuth\Providers\TotpProvider\TotpProvider;
use BetterAuth\Symfony\Controller\CredentialsController;
use BetterAuth\Symfony\Dto\Login2faRequestDto;
use BetterAuth\Symfony\Dto\LoginRequestDto;
use BetterAuth\Symfony\Dto\RegisterRequestDto;
use BetterAuth\Symfony\Tests\Controller\Trait\ControllerTestTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Unit tests for CredentialsController.
 *
 * Tests: register, login, login/2fa endpoints.
 */
class CredentialsControllerTest extends TestCase
{
    use ControllerTestTrait;

    private MockObject&AuthManager $authManager;
    private MockObject&TotpProvider $totpProvider;
    private CredentialsController $controller;

    protected function setUp(): void
    {
        $this->authManager = $this->createMock(AuthManager::class);
        $this->totpProvider = $this->createMock(TotpProvider::class);

        $this->controller = new CredentialsController(
            $this->authManager,
            $this->totpProvider,
        );
        $this->setUpControllerContainer($this->controller);
    }

    // ========================================
    // REGISTER TESTS
    // ========================================

    /**
     * @test
     */
    public function register_returns_201_on_success(): void
    {
        $user = $this->createMock(User::class);

        $this->authManager->expects($this->once())
            ->method('signUp')
            ->with('test@example.com', 'password123', [])
            ->willReturn($user);

        $this->authManager->expects($this->once())
            ->method('signIn')
            ->willReturn([
                'access_token' => 'tok_access',
                'refresh_token' => 'tok_refresh',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
                'user' => ['id' => 'uuid-1', 'email' => 'test@example.com'],
            ]);

        $dto = new RegisterRequestDto('test@example.com', 'password123');
        $request = new Request();

        $response = $this->controller->register($dto, $request);

        $this->assertSame(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('access_token', $data);
    }

    /**
     * @test
     */
    public function register_with_name_passes_name_to_signup(): void
    {
        $user = $this->createMock(User::class);

        $this->authManager->expects($this->once())
            ->method('signUp')
            ->with('test@example.com', 'password123', ['name' => 'John'])
            ->willReturn($user);

        $this->authManager->method('signIn')->willReturn([
            'access_token' => 'tok',
            'refresh_token' => 'rtok',
            'expires_in' => 3600,
        ]);

        $dto = new RegisterRequestDto('test@example.com', 'password123', 'John');
        $request = new Request();

        $response = $this->controller->register($dto, $request);

        $this->assertSame(201, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function register_returns_400_on_duplicate_email(): void
    {
        $this->authManager->method('signUp')
            ->willThrowException(new \Exception('Email already exists'));

        $dto = new RegisterRequestDto('existing@example.com', 'password123');
        $request = new Request();

        $response = $this->controller->register($dto, $request);

        $this->assertSame(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $data);
        $this->assertSame('Email already exists', $data['error']);
    }

    // ========================================
    // LOGIN TESTS
    // ========================================

    /**
     * @test
     */
    public function login_returns_200_with_tokens_on_success(): void
    {
        $tokenResult = [
            'access_token' => 'tok_access',
            'refresh_token' => 'tok_refresh',
            'expires_in' => 3600,
            'user' => ['id' => 'uuid-1', 'email' => 'test@example.com'],
        ];

        $this->authManager->expects($this->once())
            ->method('signIn')
            ->willReturn($tokenResult);

        $this->totpProvider->method('requires2fa')->willReturn(false);

        $dto = new LoginRequestDto('test@example.com', 'password123');
        $request = new Request();

        $response = $this->controller->login($dto, $request);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('access_token', $data);
    }

    /**
     * @test
     */
    public function login_returns_2fa_required_when_totp_enabled(): void
    {
        $tokenResult = [
            'access_token' => 'tok',
            'user' => ['id' => 'uuid-1', 'email' => 'test@example.com'],
        ];

        $this->authManager->method('signIn')->willReturn($tokenResult);
        $this->totpProvider->method('requires2fa')->with('uuid-1')->willReturn(true);

        $dto = new LoginRequestDto('test@example.com', 'password123');
        $request = new Request();

        $response = $this->controller->login($dto, $request);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['requires2fa']);
        $this->assertSame('Two-factor authentication required', $data['message']);
    }

    /**
     * @test
     */
    public function login_returns_401_on_invalid_credentials(): void
    {
        $this->authManager->method('signIn')
            ->willThrowException(new \Exception('Invalid credentials'));

        $dto = new LoginRequestDto('test@example.com', 'wrongpassword');
        $request = new Request();

        $response = $this->controller->login($dto, $request);

        $this->assertSame(401, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $data);
    }

    // ========================================
    // LOGIN 2FA TESTS
    // ========================================

    /**
     * @test
     */
    public function login2fa_returns_200_on_valid_code(): void
    {
        $tokenResult = [
            'access_token' => 'tok',
            'refresh_token' => 'rtok',
            'user' => ['id' => 'uuid-1', 'email' => 'test@example.com'],
        ];

        $this->authManager->method('signIn')->willReturn($tokenResult);
        $this->totpProvider->method('verify')->with('uuid-1', '123456')->willReturn(true);

        $dto = new Login2faRequestDto('test@example.com', 'password123', '123456');
        $request = new Request();

        $response = $this->controller->login2fa($dto, $request);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('access_token', $data);
    }

    /**
     * @test
     */
    public function login2fa_returns_401_on_invalid_code(): void
    {
        $tokenResult = [
            'access_token' => 'tok',
            'user' => ['id' => 'uuid-1', 'email' => 'test@example.com'],
        ];

        $this->authManager->method('signIn')->willReturn($tokenResult);
        $this->totpProvider->method('verify')->with('uuid-1', '000000')->willReturn(false);
        $this->authManager->method('revokeAllTokens')->willReturn(1);

        $dto = new Login2faRequestDto('test@example.com', 'password123', '000000');
        $request = new Request();

        $response = $this->controller->login2fa($dto, $request);

        $this->assertSame(401, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Invalid 2FA code', $data['error']);
    }

    /**
     * @test
     */
    public function login2fa_returns_401_on_exception(): void
    {
        $this->authManager->method('signIn')
            ->willThrowException(new \Exception('Auth failed'));

        $dto = new Login2faRequestDto('test@example.com', 'wrongpassword', '123456');
        $request = new Request();

        $response = $this->controller->login2fa($dto, $request);

        $this->assertSame(401, $response->getStatusCode());
    }
}
