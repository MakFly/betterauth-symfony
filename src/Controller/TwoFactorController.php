<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Controller;

use BetterAuth\Core\Entities\User;
use BetterAuth\Providers\TotpProvider\TotpProvider;
use BetterAuth\Symfony\Exception\ValidationException;
use BetterAuth\Symfony\Security\Attribute\CurrentUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Handles Two-Factor Authentication (TOTP) operations.
 */
#[Route('/auth/2fa', name: 'better_auth_2fa_')]
class TwoFactorController extends AbstractController
{
    public function __construct(
        private readonly TotpProvider $totpProvider,
    ) {
    }

    #[Route('/setup', name: 'setup', methods: ['POST'])]
    public function setup(#[CurrentUser] User $user): JsonResponse
    {
        $result = $this->totpProvider->generateSecret($user->getId(), $user->getEmail());

        return $this->json([
            'secret' => $result['secret'],
            'qrCode' => $result['qrCode'],
            'manualEntryKey' => $result['manualEntryKey'] ?? $result['secret'],
            'backupCodes' => $result['backupCodes'],
        ]);
    }

    #[Route('/validate', name: 'validate', methods: ['POST'])]
    public function validate(#[CurrentUser] User $user, Request $request): JsonResponse
    {
        $data = $request->toArray();
        $verified = $this->totpProvider->verifyAndEnable($user->getId(), $data['code']);

        if (!$verified) {
            throw new ValidationException('Invalid verification code');
        }

        return $this->json([
            'message' => 'Two-factor authentication enabled successfully',
            'enabled' => true,
        ]);
    }

    #[Route('/verify', name: 'verify', methods: ['POST'])]
    public function verify(#[CurrentUser] User $user, Request $request): JsonResponse
    {
        $data = $request->toArray();
        $verified = $this->totpProvider->verify($user->getId(), $data['code']);

        if (!$verified) {
            throw new ValidationException('Invalid verification code');
        }

        return $this->json([
            'message' => 'Code verified successfully',
            'success' => true,
        ]);
    }

    #[Route('/disable', name: 'disable', methods: ['POST'])]
    public function disable(#[CurrentUser] User $user, Request $request): JsonResponse
    {
        $data = $request->toArray();
        $disabled = $this->totpProvider->disable($user->getId(), $data['backupCode']);

        if (!$disabled) {
            throw new ValidationException('Invalid backup code');
        }

        return $this->json([
            'message' => 'Two-factor authentication disabled successfully',
            'enabled' => false,
        ]);
    }

    #[Route('/backup-codes/regenerate', name: 'regenerate_backup_codes', methods: ['POST'])]
    public function regenerateBackupCodes(#[CurrentUser] User $user, Request $request): JsonResponse
    {
        $data = $request->toArray();
        $result = $this->totpProvider->regenerateBackupCodes($user->getId(), $data['code']);

        if (!$result['success']) {
            throw new ValidationException('Invalid verification code');
        }

        return $this->json([
            'message' => 'Backup codes regenerated successfully',
            'backupCodes' => $result['backupCodes'],
        ]);
    }

    #[Route('/status', name: 'status', methods: ['GET'])]
    public function status(#[CurrentUser] User $user): JsonResponse
    {
        $status = $this->totpProvider->getStatus($user->getId());

        return $this->json([
            'enabled' => $status['enabled'],
            'backupCodesRemaining' => $status['backupCodesRemaining'] ?? 0,
            'requires2fa' => $status['requires2fa'] ?? false,
            'last2faVerifiedAt' => $status['last2faVerifiedAt'] ?? null,
        ]);
    }

    #[Route('/reset', name: 'reset', methods: ['POST'])]
    public function reset(#[CurrentUser] User $user, Request $request): JsonResponse
    {
        $data = $request->toArray();
        $result = $this->totpProvider->resetWithBackupCode(
            $user->getId(),
            $data['backupCode'],
            $user->getEmail()
        );

        if (!$result['success']) {
            throw new ValidationException($result['error'] ?? 'Failed to reset 2FA');
        }

        return $this->json([
            'message' => '2FA reset successfully. Please validate the new setup.',
            'secret' => $result['secret'],
            'qrCode' => $result['qrCode'],
            'manualEntryKey' => $result['manualEntryKey'],
            'backupCodes' => $result['backupCodes'],
        ]);
    }
}
