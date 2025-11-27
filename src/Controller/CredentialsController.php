<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Controller;

use BetterAuth\Core\AuthManager;
use BetterAuth\Core\Entities\User;
use BetterAuth\Providers\TotpProvider\TotpProvider;
use BetterAuth\Symfony\Controller\Trait\AuthResponseTrait;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/auth', name: 'better_auth_')]
class CredentialsController extends AbstractController
{
    use AuthResponseTrait;

    public function __construct(
        private readonly AuthManager $authManager,
        private readonly TotpProvider $totpProvider,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    #[Route('/register', name: 'register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $data = $request->toArray();

        if (!isset($data['email'], $data['password'])) {
            return $this->json(['error' => 'Email and password are required'], 400);
        }

        try {
            $additionalData = isset($data['name']) ? ['name' => $data['name']] : [];

            $user = $this->authManager->signUp(
                $data['email'],
                $data['password'],
                $additionalData
            );

            $result = $this->authManager->signIn(
                $data['email'],
                $data['password'],
                $request->getClientIp() ?? '127.0.0.1',
                $request->headers->get('User-Agent') ?? 'Unknown'
            );

            // $result already contains formatted user data (password excluded)
            return $this->json($result, 201);
        } catch (\Exception $e) {
            $this->logger?->error('Registration failed', [
                'email' => $data['email'],
                'error' => $e->getMessage(),
            ]);
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/login', name: 'login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $data = $request->toArray();

        if (!isset($data['email'], $data['password'])) {
            return $this->json(['error' => 'Email and password are required'], 400);
        }

        try {
            $result = $this->authManager->signIn(
                $data['email'],
                $data['password'],
                $request->getClientIp() ?? '127.0.0.1',
                $request->headers->get('User-Agent') ?? 'Unknown'
            );

            // $result['user'] is already a DTO array (password excluded)
            $userData = $result['user'];
            $userId = $userData['id'];

            if ($this->totpProvider->requires2fa($userId)) {
                return $this->json([
                    'requires2fa' => true,
                    'message' => 'Two-factor authentication required',
                    'user' => $userData,
                ]);
            }

            // $result already contains formatted user data (password excluded)
            return $this->json($result);
        } catch (\Exception $e) {
            $this->logger?->error('Login failed', [
                'email' => $data['email'],
                'error' => $e->getMessage(),
            ]);
            return $this->json(['error' => $e->getMessage()], 401);
        }
    }

    #[Route('/login/2fa', name: 'login_2fa', methods: ['POST'])]
    public function login2fa(Request $request): JsonResponse
    {
        $data = $request->toArray();

        if (!isset($data['email'], $data['password'], $data['code'])) {
            return $this->json(['error' => 'Email, password and 2FA code are required'], 400);
        }

        try {
            $result = $this->authManager->signIn(
                $data['email'],
                $data['password'],
                $request->getClientIp() ?? '127.0.0.1',
                $request->headers->get('User-Agent') ?? 'Unknown'
            );

            // $result['user'] is already a DTO array (password excluded)
            $userData = $result['user'];
            $userId = $userData['id'];

            $verified = $this->totpProvider->verify($userId, $data['code']);
            if (!$verified) {
                if (isset($result['session'])) {
                    $this->authManager->signOut($result['session']->getToken());
                } elseif (isset($result['access_token'])) {
                    $this->authManager->revokeAllTokens($userId);
                }
                return $this->json(['error' => 'Invalid 2FA code'], 401);
            }

            // $result already contains formatted user data (password excluded)
            return $this->json($result);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 401);
        }
    }
}
