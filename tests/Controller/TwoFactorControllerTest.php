<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Tests\Controller;

use BetterAuth\Core\AuthManager;
use BetterAuth\Core\Entities\User;
use BetterAuth\Providers\TotpProvider\TotpProvider;
use BetterAuth\Symfony\Controller\TwoFactorController;
use BetterAuth\Symfony\Tests\Controller\Trait\ControllerTestTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Unit tests for TwoFactorController.
 *
 * Tests: setup, validate, verify, disable, regenerate backup codes, status, reset.
 */
class TwoFactorControllerTest extends TestCase
{
    use ControllerTestTrait;

    private MockObject&AuthManager $authManager;
    private MockObject&TotpProvider $totpProvider;
    private TwoFactorController $controller;

    protected function setUp(): void
    {
        $this->authManager = $this->createMock(AuthManager::class);
        $this->totpProvider = $this->createMock(TotpProvider::class);
        $this->controller = new TwoFactorController(
            $this->authManager,
            $this->totpProvider,
        );
        $this->setUpControllerContainer($this->controller);
    }

    private function createAuthenticatedRequest(string $token = 'valid-token', array $body = []): Request
    {
        $request = new Request(content: json_encode($body));
        $request->headers->set('Authorization', "Bearer $token");
        return $request;
    }

    private function createMockUser(string $id = 'uuid-1', string $email = 'test@example.com'): MockObject&User
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn($id);
        $user->method('getEmail')->willReturn($email);
        return $user;
    }

    private function configureAuth(MockObject&User $user): void
    {
        $this->authManager->method('getCurrentUser')->willReturn($user);
    }

    // ========================================
    // SETUP TESTS
    // ========================================

    /**
     * @test
     */
    public function setup_returns_qr_code_and_secret(): void
    {
        $user = $this->createMockUser();
        $this->configureAuth($user);

        $this->totpProvider->expects($this->once())
            ->method('generateSecret')
            ->with('uuid-1', 'test@example.com')
            ->willReturn([
                'secret' => 'BASE32SECRET',
                'qrCode' => 'data:image/png;base64,...',
                'manualEntryKey' => 'BASE32SECRET',
                'backupCodes' => ['code1', 'code2'],
            ]);

        $request = $this->createAuthenticatedRequest();
        $response = $this->controller->setup($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('secret', $data);
        $this->assertArrayHasKey('qrCode', $data);
        $this->assertArrayHasKey('backupCodes', $data);
    }

    /**
     * @test
     */
    public function setup_returns_401_when_no_token(): void
    {
        $request = new Request();
        $response = $this->controller->setup($request);

        $this->assertSame(401, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('No token provided', $data['error']);
    }

    /**
     * @test
     */
    public function setup_returns_401_when_user_not_found(): void
    {
        $this->authManager->method('getCurrentUser')->willReturn(null);

        $request = $this->createAuthenticatedRequest();
        $response = $this->controller->setup($request);

        $this->assertSame(401, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Invalid token', $data['error']);
    }

    // ========================================
    // VALIDATE TESTS
    // ========================================

    /**
     * @test
     */
    public function validate_returns_200_on_valid_code(): void
    {
        $user = $this->createMockUser();
        $this->configureAuth($user);
        $this->totpProvider->method('verifyAndEnable')->with('uuid-1', '123456')->willReturn(true);

        $request = $this->createAuthenticatedRequest('valid-token', ['code' => '123456']);
        $response = $this->controller->validate($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['enabled']);
        $this->assertStringContainsString('enabled successfully', $data['message']);
    }

    /**
     * @test
     */
    public function validate_returns_400_on_invalid_code(): void
    {
        $user = $this->createMockUser();
        $this->configureAuth($user);
        $this->totpProvider->method('verifyAndEnable')->willReturn(false);

        $request = $this->createAuthenticatedRequest('valid-token', ['code' => '000000']);
        $response = $this->controller->validate($request);

        $this->assertSame(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Invalid verification code', $data['error']);
    }

    /**
     * @test
     */
    public function validate_returns_400_when_code_missing(): void
    {
        $user = $this->createMockUser();
        $this->configureAuth($user);

        $request = $this->createAuthenticatedRequest('valid-token', []);
        $response = $this->controller->validate($request);

        $this->assertSame(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Verification code is required', $data['error']);
    }

    // ========================================
    // VERIFY TESTS
    // ========================================

    /**
     * @test
     */
    public function verify_returns_200_on_valid_code(): void
    {
        $user = $this->createMockUser();
        $this->configureAuth($user);
        $this->totpProvider->method('verify')->with('uuid-1', '654321')->willReturn(true);

        $request = $this->createAuthenticatedRequest('valid-token', ['code' => '654321']);
        $response = $this->controller->verify($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
    }

    /**
     * @test
     */
    public function verify_returns_400_on_invalid_code(): void
    {
        $user = $this->createMockUser();
        $this->configureAuth($user);
        $this->totpProvider->method('verify')->willReturn(false);

        $request = $this->createAuthenticatedRequest('valid-token', ['code' => '000000']);
        $response = $this->controller->verify($request);

        $this->assertSame(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Invalid verification code', $data['error']);
    }

    /**
     * @test
     */
    public function verify_returns_401_when_no_token(): void
    {
        $request = new Request(content: json_encode(['code' => '123456']));
        $response = $this->controller->verify($request);

        $this->assertSame(401, $response->getStatusCode());
    }

    // ========================================
    // DISABLE TESTS
    // ========================================

    /**
     * @test
     */
    public function disable_returns_200_on_valid_backup_code(): void
    {
        $user = $this->createMockUser();
        $this->configureAuth($user);
        $this->totpProvider->method('disable')->with('uuid-1', 'backup-code-1')->willReturn(true);

        $request = $this->createAuthenticatedRequest('valid-token', ['backupCode' => 'backup-code-1']);
        $response = $this->controller->disable($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['enabled']);
        $this->assertStringContainsString('disabled successfully', $data['message']);
    }

    /**
     * @test
     */
    public function disable_returns_400_on_invalid_backup_code(): void
    {
        $user = $this->createMockUser();
        $this->configureAuth($user);
        $this->totpProvider->method('disable')->willReturn(false);

        $request = $this->createAuthenticatedRequest('valid-token', ['backupCode' => 'wrong-code']);
        $response = $this->controller->disable($request);

        $this->assertSame(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Invalid backup code', $data['error']);
    }

    /**
     * @test
     */
    public function disable_returns_400_when_backup_code_missing(): void
    {
        $user = $this->createMockUser();
        $this->configureAuth($user);

        $request = $this->createAuthenticatedRequest('valid-token', []);
        $response = $this->controller->disable($request);

        $this->assertSame(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Backup code is required to disable 2FA', $data['error']);
    }

    // ========================================
    // REGENERATE BACKUP CODES TESTS
    // ========================================

    /**
     * @test
     */
    public function regenerate_backup_codes_returns_new_codes(): void
    {
        $user = $this->createMockUser();
        $this->configureAuth($user);
        $this->totpProvider->method('regenerateBackupCodes')
            ->with('uuid-1', '123456')
            ->willReturn([
                'success' => true,
                'backupCodes' => ['new-code-1', 'new-code-2'],
            ]);

        $request = $this->createAuthenticatedRequest('valid-token', ['code' => '123456']);
        $response = $this->controller->regenerateBackupCodes($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(2, $data['backupCodes']);
        $this->assertStringContainsString('regenerated', $data['message']);
    }

    /**
     * @test
     */
    public function regenerate_backup_codes_returns_400_on_invalid_code(): void
    {
        $user = $this->createMockUser();
        $this->configureAuth($user);
        $this->totpProvider->method('regenerateBackupCodes')
            ->willReturn(['success' => false]);

        $request = $this->createAuthenticatedRequest('valid-token', ['code' => '000000']);
        $response = $this->controller->regenerateBackupCodes($request);

        $this->assertSame(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Invalid verification code', $data['error']);
    }

    // ========================================
    // STATUS TESTS
    // ========================================

    /**
     * @test
     */
    public function status_returns_2fa_enabled_status(): void
    {
        $user = $this->createMockUser();
        $this->configureAuth($user);
        $this->totpProvider->method('getStatus')
            ->with('uuid-1')
            ->willReturn([
                'enabled' => true,
                'backupCodesRemaining' => 8,
                'requires2fa' => true,
                'last2faVerifiedAt' => '2024-01-15T10:00:00Z',
            ]);

        $request = $this->createAuthenticatedRequest();
        $response = $this->controller->status($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['enabled']);
        $this->assertSame(8, $data['backupCodesRemaining']);
        $this->assertTrue($data['requires2fa']);
    }

    /**
     * @test
     */
    public function status_returns_defaults_when_2fa_not_setup(): void
    {
        $user = $this->createMockUser();
        $this->configureAuth($user);
        $this->totpProvider->method('getStatus')
            ->willReturn(['enabled' => false]);

        $request = $this->createAuthenticatedRequest();
        $response = $this->controller->status($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['enabled']);
        $this->assertSame(0, $data['backupCodesRemaining']);
        $this->assertFalse($data['requires2fa']);
    }

    // ========================================
    // RESET TESTS
    // ========================================

    /**
     * @test
     */
    public function reset_returns_new_qr_code_on_valid_backup_code(): void
    {
        $user = $this->createMockUser();
        $this->configureAuth($user);
        $this->totpProvider->method('resetWithBackupCode')
            ->with('uuid-1', 'valid-backup', 'test@example.com')
            ->willReturn([
                'success' => true,
                'secret' => 'NEWSECRET',
                'qrCode' => 'data:image/png...',
                'manualEntryKey' => 'NEWSECRET',
                'backupCodes' => ['bc1', 'bc2'],
            ]);

        $request = $this->createAuthenticatedRequest('valid-token', ['backupCode' => 'valid-backup']);
        $response = $this->controller->reset($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('secret', $data);
        $this->assertArrayHasKey('qrCode', $data);
        $this->assertArrayHasKey('backupCodes', $data);
    }

    /**
     * @test
     */
    public function reset_returns_400_when_backup_code_missing(): void
    {
        $user = $this->createMockUser();
        $this->configureAuth($user);

        $request = $this->createAuthenticatedRequest('valid-token', []);
        $response = $this->controller->reset($request);

        $this->assertSame(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Backup code is required to reset 2FA', $data['error']);
    }

    /**
     * @test
     */
    public function reset_returns_400_on_invalid_backup_code(): void
    {
        $user = $this->createMockUser();
        $this->configureAuth($user);
        $this->totpProvider->method('resetWithBackupCode')
            ->willReturn(['success' => false, 'error' => 'Invalid backup code']);

        $request = $this->createAuthenticatedRequest('valid-token', ['backupCode' => 'wrong-code']);
        $response = $this->controller->reset($request);

        $this->assertSame(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Invalid backup code', $data['error']);
    }
}
