<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Controller;

use BetterAuth\Core\AuthManager;
use BetterAuth\Providers\EmailVerificationProvider\EmailVerificationProvider;
use BetterAuth\Symfony\Controller\Trait\AuthResponseTrait;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/auth/email', name: 'better_auth_email_')]
class EmailVerificationController extends AbstractController
{
    use AuthResponseTrait;

    public function __construct(
        private readonly AuthManager $authManager,
        private readonly EmailVerificationProvider $emailVerificationProvider,
        private readonly ?LoggerInterface $logger = null,
        #[Autowire(env: 'FRONTEND_URL')]
        private readonly string $frontendUrl = 'http://localhost:5173',
    ) {
    }

    #[Route('/send-verification', name: 'send_verification', methods: ['POST'])]
    public function sendVerification(Request $request): JsonResponse
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

            if ($user->isEmailVerified()) {
                return $this->json(['error' => 'Email already verified'], 400);
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
        } catch (\Exception $e) {
            $this->logger?->error('Failed to send verification email', [
                'error' => $e->getMessage(),
            ]);
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/verify', name: 'verify', methods: ['POST'])]
    public function verify(Request $request): JsonResponse
    {
        try {
            $data = $request->toArray();

            if (!isset($data['token'])) {
                return $this->json(['error' => 'Verification token is required'], 400);
            }

            $result = $this->emailVerificationProvider->verifyEmail($data['token']);

            if (!$result['success']) {
                return $this->json(['error' => $result['error'] ?? 'Invalid or expired token'], 400);
            }

            return $this->json([
                'message' => 'Email verified successfully',
                'verified' => true,
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/verification-status', name: 'verification_status', methods: ['GET'])]
    public function verificationStatus(Request $request): JsonResponse
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

            return $this->json([
                'verified' => $user->isEmailVerified(),
                'email' => $user->getEmail(),
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }
}
