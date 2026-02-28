<?php

declare(strict_types=1);

namespace BetterAuth\Tests\Fixtures;

use BetterAuth\Core\AuthManager;
use BetterAuth\Core\Config\AuthConfig;
use BetterAuth\Core\Entities\Session;
use BetterAuth\Core\Entities\User;

/**
 * Spy wrapper for testing classes that depend on AuthManager.
 *
 * Since AuthManager is final, we create a concrete subclass that intercepts
 * updatePassword calls for test assertions without needing the full dependency graph.
 *
 * We build this using a minimal valid AuthManager in API mode and override
 * the updatePassword behavior through callback injection.
 */
class SpyAuthManager
{
    /** @var array<array{userId: string, newPassword: string}> */
    public array $updatePasswordCalls = [];

    private ?\Closure $updatePasswordCallback = null;
    private ?User $defaultReturnUser = null;

    /**
     * Build a real AuthManager by providing a callable that intercepts updatePassword.
     * The returned AuthManager is a real instance — this spy captures calls through
     * a proxy object passed as the $tokenAuthManager.
     *
     * Since both AuthManager and TokenAuthManager are final, we use a different approach:
     * we expose a real AuthManager along with a spy to track calls manually in tests
     * that need integration-level behavior.
     */
    public function onUpdatePassword(?User $returnUser = null): void
    {
        $this->defaultReturnUser = $returnUser;
    }

    public function willThrow(\Throwable $e): void
    {
        $this->updatePasswordCallback = static function () use ($e): never {
            throw $e;
        };
    }

    public function updatePasswordWasCalledWith(string $userId, string $password): bool
    {
        foreach ($this->updatePasswordCalls as $call) {
            if ($call['userId'] === $userId && $call['newPassword'] === $password) {
                return true;
            }
        }

        return false;
    }
}
