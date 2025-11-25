<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Controller;

use BetterAuth\Core\Entities\User;
use BetterAuth\Providers\EmailVerificationProvider\EmailVerificationProvider;
use BetterAuth\Symfony\Exception\ValidationException;
use BetterAuth\Symfony\Security\Attribute\CurrentUser;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Handles email verification: send verification email, verify token, check status.
 */
#[Route('/auth/email', name: 'better_auth_email_')]
class EmailVerificationController extends AbstractController
{
    public function __construct(
        private readonly EmailVerificationProvider $emailVerificationProvider,
        private readonly ?LoggerInterface $logger = null,
        #[Autowire(env: 'FRONTEND_URL')]
        private readonly string $frontendUrl = 'http://localhost:5173',
    ) {
    }

    #[Route('/send-verification', name: 'send_verification', methods: ['POST'])]
    public function sendVerification(#[CurrentUser] User $user, Request $request): JsonResponse
    {
        if ($user->isEmailVerified()) {
            throw new ValidationException('Email already verified');
        }

        $data = $request->toArray();
        $callbackUrl = $data['callbackUrl'] ?? rtrim($this->frontendUrl, '/') . '/auth/email/verify';

        $this->logger?->info('Sending verification email', [
            'userId' => $user->getId(),
            'email' => $user->getEmail(),
        ]);

        $result = $this->emailVerificationProvider->sendVerificationEmail(
            $user->getId(),
            $user->getEmail(),
            $callbackUrl
        );

        return $this->json([
            'message' => 'Verification email sent successfully',
            'expiresIn' => $result['expiresIn'] ?? 3600,
        ]);
    }

    #[Route('/verify', name: 'verify', methods: ['POST'])]
    public function verify(Request $request): JsonResponse
    {
        $data = $request->toArray();
        $result = $this->emailVerificationProvider->verifyEmail($data['token']);

        if (!$result['success']) {
            throw new ValidationException($result['error'] ?? 'Invalid or expired token');
        }

        return $this->json([
            'message' => 'Email verified successfully',
            'verified' => true,
        ]);
    }

    #[Route('/verification-status', name: 'verification_status', methods: ['GET'])]
    public function verificationStatus(#[CurrentUser] User $user): JsonResponse
    {
        return $this->json([
            'verified' => $user->isEmailVerified(),
            'email' => $user->getEmail(),
        ]);
    }
}
