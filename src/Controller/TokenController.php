<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Controller;

use BetterAuth\Core\AuthManager;
use BetterAuth\Symfony\Controller\Trait\AuthResponseTrait;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/auth', name: 'better_auth_')]
class TokenController extends AbstractController
{
    use AuthResponseTrait;

    public function __construct(
        private readonly AuthManager $authManager,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    #[Route('/me', name: 'me', methods: ['GET'])]
    public function me(Request $request): JsonResponse
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

            return $this->json($this->formatUser($user));
        } catch (\Exception $e) {
            $this->logger?->warning('Failed to get current user', [
                'error' => $e->getMessage(),
            ]);
            return $this->json(['error' => $e->getMessage()], 401);
        }
    }

    #[Route('/refresh', name: 'refresh', methods: ['POST'])]
    public function refresh(Request $request): JsonResponse
    {
        $data = $request->toArray();

        if (!isset($data['refreshToken'])) {
            return $this->json(['error' => 'Refresh token is required'], 400);
        }

        try {
            $result = $this->authManager->refresh($data['refreshToken']);

            $this->logger?->info('Token refreshed successfully');

            return $this->json($result);
        } catch (\Exception $e) {
            $this->logger?->warning('Token refresh failed', [
                'error' => $e->getMessage(),
            ]);
            return $this->json(['error' => $e->getMessage()], 401);
        }
    }

    #[Route('/logout', name: 'logout', methods: ['POST'])]
    public function logout(Request $request): JsonResponse
    {
        try {
            $token = $this->extractBearerToken($request);
            if (!$token) {
                return $this->json(['error' => 'No token provided'], 401);
            }

            $this->authManager->signOut($token);

            $this->logger?->info('User logged out successfully');

            return $this->json(['message' => 'Logged out successfully']);
        } catch (\Exception $e) {
            $this->logger?->error('Logout failed', [
                'error' => $e->getMessage(),
            ]);
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/revoke-all', name: 'revoke_all', methods: ['POST'])]
    public function revokeAll(Request $request): JsonResponse
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

            $count = $this->authManager->revokeAllTokens($user->getId());

            $this->logger?->info('All sessions revoked', [
                'userId' => $user->getId(),
                'count' => $count,
            ]);

            return $this->json([
                'message' => 'All sessions revoked successfully',
                'count' => $count,
            ]);
        } catch (\Exception $e) {
            $this->logger?->error('Failed to revoke all sessions', [
                'error' => $e->getMessage(),
            ]);
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }
}
