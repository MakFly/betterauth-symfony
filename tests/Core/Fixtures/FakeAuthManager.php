<?php

declare(strict_types=1);

namespace BetterAuth\Tests\Fixtures;

use BetterAuth\Core\AuthManager;
use BetterAuth\Core\Config\AuthConfig;
use BetterAuth\Core\Entities\User;
use BetterAuth\Core\TokenAuthManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Fake AuthManager for unit testing classes that depend on AuthManager.
 *
 * Since AuthManager is declared final, we cannot mock it directly.
 * This fake wraps a TokenAuthManager mock to allow testing updatePassword behavior.
 */
class FakeAuthManager
{
    /** @var array<array{userId: string, newPassword: string}> */
    public array $updatePasswordCalls = [];

    private ?User $updatePasswordReturnValue = null;
    private ?\Throwable $updatePasswordException = null;

    public function updatePassword(string $userId, string $newPassword): ?User
    {
        $this->updatePasswordCalls[] = [
            'userId' => $userId,
            'newPassword' => $newPassword,
        ];

        if ($this->updatePasswordException !== null) {
            throw $this->updatePasswordException;
        }

        return $this->updatePasswordReturnValue;
    }

    public function willReturnUser(?User $user): void
    {
        $this->updatePasswordReturnValue = $user;
    }

    public function willThrow(\Throwable $e): void
    {
        $this->updatePasswordException = $e;
    }

    public function wasCalledWith(string $userId, string $newPassword): bool
    {
        foreach ($this->updatePasswordCalls as $call) {
            if ($call['userId'] === $userId && $call['newPassword'] === $newPassword) {
                return true;
            }
        }

        return false;
    }

    /**
     * Create a real AuthManager configured for API mode with a mocked TokenAuthManager.
     * This approach delegates actual AuthManager behavior for integration-level tests.
     */
    public static function createApiAuthManager(
        TestCase $testCase,
        TokenAuthManager&MockObject $tokenAuthManager,
    ): AuthManager {
        $config = AuthConfig::forApi('test-secret-key-32chars-minimum!!');

        return new AuthManager($config, null, $tokenAuthManager);
    }
}
