<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Controller;

use BetterAuth\Providers\PasswordResetProvider\PasswordResetProvider;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/auth/password', name: 'better_auth_password_')]
class PasswordResetController extends AbstractController
{
    public function __construct(
        private readonly PasswordResetProvider $passwordResetProvider,
        private readonly ?LoggerInterface $logger = null,
        #[Autowire(env: 'FRONTEND_URL')]
        private readonly string $frontendUrl = 'http://localhost:5173',
    ) {
    }

    #[Route('/forgot', name: 'forgot', methods: ['POST'])]
    public function forgotPassword(Request $request): JsonResponse
    {
        try {
            $data = $request->toArray();

            if (!isset($data['email'])) {
                return $this->json(['error' => 'Email is required'], 400);
            }

            $callbackUrl = rtrim($this->frontendUrl, '/') . '/reset-password';
            $this->passwordResetProvider->sendResetEmail($data['email'], $callbackUrl);

            // Always return success to prevent email enumeration
            return $this->json([
                'message' => 'If an account exists with this email, a password reset link has been sent',
                'expiresIn' => 3600,
            ]);
        } catch (\Exception $e) {
            // Don't expose internal errors for security
            return $this->json([
                'message' => 'If an account exists with this email, a password reset link has been sent',
            ]);
        }
    }

    #[Route('/reset', name: 'reset', methods: ['POST'])]
    public function resetPassword(Request $request): JsonResponse
    {
        try {
            $data = $request->toArray();

            if (!isset($data['token'], $data['newPassword'])) {
                return $this->json(['error' => 'Token and new password are required'], 400);
            }

            if (strlen($data['newPassword']) < 8) {
                return $this->json(['error' => 'Password must be at least 8 characters long'], 400);
            }

            $result = $this->passwordResetProvider->resetPassword(
                $data['token'],
                $data['newPassword']
            );

            if (!$result['success']) {
                return $this->json([
                    'error' => $result['error'] ?? 'Invalid or expired reset token',
                ], 400);
            }

            return $this->json([
                'message' => 'Password reset successfully',
                'success' => true,
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/verify-token', name: 'verify_token', methods: ['POST'])]
    public function verifyResetToken(Request $request): JsonResponse
    {
        try {
            $data = $request->toArray();

            if (!isset($data['token'])) {
                return $this->json(['error' => 'Token is required'], 400);
            }

            $result = $this->passwordResetProvider->verifyResetToken($data['token']);

            if (!$result['valid']) {
                return $this->json([
                    'valid' => false,
                    'error' => 'Invalid or expired token',
                ], 400);
            }

            return $this->json([
                'valid' => true,
                'email' => $result['email'] ?? null,
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }
}
