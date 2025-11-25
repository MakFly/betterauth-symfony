<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Controller;

use BetterAuth\Core\AuthManager;
use BetterAuth\Providers\TotpProvider\TotpProvider;
use BetterAuth\Symfony\Controller\Trait\AuthResponseTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/auth/2fa', name: 'better_auth_2fa_')]
class TwoFactorController extends AbstractController
{
    use AuthResponseTrait;

    public function __construct(
        private readonly AuthManager $authManager,
        private readonly TotpProvider $totpProvider,
    ) {
    }

    #[Route('/setup', name: 'setup', methods: ['POST'])]
    public function setup(Request $request): JsonResponse
    {
        try {
            $token = $this->extractBearerToken($request);
            if (!$token) {
                return $this->json(['error' => 'No token provided'], 401);
            }

            $user = $this->authManager->getCurrentUser($token);
            if (!$user) {
                return $this->json(['error' => 'Invalid token'], 401);
            }

            $result = $this->totpProvider->generateSecret($user->getId(), $user->getEmail());

            return $this->json([
                'secret' => $result['secret'],
                'qrCode' => $result['qrCode'],
                'manualEntryKey' => $result['manualEntryKey'] ?? $result['secret'],
                'backupCodes' => $result['backupCodes'] ?? [],
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/validate', name: 'validate', methods: ['POST'])]
    public function validate(Request $request): JsonResponse
    {
        try {
            $token = $this->extractBearerToken($request);
            if (!$token) {
                return $this->json(['error' => 'No token provided'], 401);
            }

            $user = $this->authManager->getCurrentUser($token);
            if (!$user) {
                return $this->json(['error' => 'Invalid token'], 401);
            }

            $data = $request->toArray();
            if (!isset($data['code'])) {
                return $this->json(['error' => 'Verification code is required'], 400);
            }

            $verified = $this->totpProvider->verifyAndEnable($user->getId(), $data['code']);

            if (!$verified) {
                return $this->json(['error' => 'Invalid verification code'], 400);
            }

            return $this->json([
                'message' => 'Two-factor authentication enabled successfully',
                'enabled' => true,
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/verify', name: 'verify', methods: ['POST'])]
    public function verify(Request $request): JsonResponse
    {
        try {
            $token = $this->extractBearerToken($request);
            if (!$token) {
                return $this->json(['error' => 'No token provided'], 401);
            }

            $user = $this->authManager->getCurrentUser($token);
            if (!$user) {
                return $this->json(['error' => 'Invalid token'], 401);
            }

            $data = $request->toArray();
            if (!isset($data['code'])) {
                return $this->json(['error' => 'Verification code is required'], 400);
            }

            $verified = $this->totpProvider->verify($user->getId(), $data['code']);

            if (!$verified) {
                return $this->json(['error' => 'Invalid verification code'], 400);
            }

            return $this->json([
                'message' => 'Code verified successfully',
                'success' => true,
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/disable', name: 'disable', methods: ['POST'])]
    public function disable(Request $request): JsonResponse
    {
        try {
            $token = $this->extractBearerToken($request);
            if (!$token) {
                return $this->json(['error' => 'No token provided'], 401);
            }

            $user = $this->authManager->getCurrentUser($token);
            if (!$user) {
                return $this->json(['error' => 'Invalid token'], 401);
            }

            $data = $request->toArray();
            if (!isset($data['backupCode'])) {
                return $this->json(['error' => 'Backup code is required to disable 2FA'], 400);
            }

            $disabled = $this->totpProvider->disable($user->getId(), $data['backupCode']);

            if (!$disabled) {
                return $this->json(['error' => 'Invalid backup code'], 400);
            }

            return $this->json([
                'message' => 'Two-factor authentication disabled successfully',
                'enabled' => false,
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/backup-codes/regenerate', name: 'regenerate_backup_codes', methods: ['POST'])]
    public function regenerateBackupCodes(Request $request): JsonResponse
    {
        try {
            $token = $this->extractBearerToken($request);
            if (!$token) {
                return $this->json(['error' => 'No token provided'], 401);
            }

            $user = $this->authManager->getCurrentUser($token);
            if (!$user) {
                return $this->json(['error' => 'Invalid token'], 401);
            }

            $data = $request->toArray();
            if (!isset($data['code'])) {
                return $this->json(['error' => 'Verification code is required'], 400);
            }

            $result = $this->totpProvider->regenerateBackupCodes($user->getId(), $data['code']);

            if (!$result['success']) {
                return $this->json(['error' => 'Invalid verification code'], 400);
            }

            return $this->json([
                'message' => 'Backup codes regenerated successfully',
                'backupCodes' => $result['backupCodes'],
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/status', name: 'status', methods: ['GET'])]
    public function status(Request $request): JsonResponse
    {
        try {
            $token = $this->extractBearerToken($request);
            if (!$token) {
                return $this->json(['error' => 'No token provided'], 401);
            }

            $user = $this->authManager->getCurrentUser($token);
            if (!$user) {
                return $this->json(['error' => 'Invalid token'], 401);
            }

            $status = $this->totpProvider->getStatus($user->getId());

            return $this->json([
                'enabled' => $status['enabled'],
                'backupCodesRemaining' => $status['backupCodesRemaining'] ?? 0,
                'requires2fa' => $status['requires2fa'] ?? false,
                'last2faVerifiedAt' => $status['last2faVerifiedAt'] ?? null,
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/reset', name: 'reset', methods: ['POST'])]
    public function reset(Request $request): JsonResponse
    {
        try {
            $token = $this->extractBearerToken($request);
            if (!$token) {
                return $this->json(['error' => 'No token provided'], 401);
            }

            $user = $this->authManager->getCurrentUser($token);
            if (!$user) {
                return $this->json(['error' => 'Invalid token'], 401);
            }

            $data = $request->toArray();
            if (!isset($data['backupCode'])) {
                return $this->json(['error' => 'Backup code is required to reset 2FA'], 400);
            }

            $result = $this->totpProvider->resetWithBackupCode($user->getId(), $data['backupCode'], $user->getEmail());

            if (!$result['success']) {
                return $this->json(['error' => $result['error'] ?? 'Failed to reset 2FA'], 400);
            }

            return $this->json([
                'message' => '2FA reset successfully. Please validate the new setup.',
                'secret' => $result['secret'],
                'qrCode' => $result['qrCode'],
                'manualEntryKey' => $result['manualEntryKey'],
                'backupCodes' => $result['backupCodes'],
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }
}
