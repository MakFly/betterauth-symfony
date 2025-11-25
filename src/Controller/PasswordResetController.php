<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Controller;

use BetterAuth\Providers\PasswordResetProvider\PasswordResetProvider;
use BetterAuth\Symfony\Exception\ValidationException;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Handles password reset flow: forgot password, reset, verify token.
 */
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
        $data = $request->toArray();

        $this->logger?->info('Password reset requested', ['email' => $data['email']]);

        $callbackUrl = $data['callbackUrl'] ?? rtrim($this->frontendUrl, '/') . '/reset-password';

        // Always return success to prevent email enumeration
        $this->passwordResetProvider->sendResetEmail($data['email'], $callbackUrl);

        return $this->json([
            'message' => 'If an account exists with this email, a password reset link has been sent',
            'expiresIn' => 3600,
        ]);
    }

    #[Route('/reset', name: 'reset', methods: ['POST'])]
    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->toArray();
        $result = $this->passwordResetProvider->resetPassword($data['token'], $data['newPassword']);

        if (!$result['success']) {
            throw new ValidationException($result['error'] ?? 'Invalid or expired reset token');
        }

        return $this->json([
            'message' => 'Password reset successfully',
            'success' => true,
        ]);
    }

    #[Route('/verify-token', name: 'verify_token', methods: ['POST'])]
    public function verifyResetToken(Request $request): JsonResponse
    {
        $data = $request->toArray();
        $result = $this->passwordResetProvider->verifyResetToken($data['token']);

        if (!$result['valid']) {
            throw new ValidationException('Invalid or expired token');
        }

        return $this->json([
            'valid' => true,
            'email' => $result['email'] ?? null,
        ]);
    }
}
